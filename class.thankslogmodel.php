<?php if (!defined('APPLICATION')) exit();

class ThanksLogModel extends Gdn_Model {
	
	public function __construct() {
		parent::__construct('ThanksLog');
	}
	
	public static function GetObjectInserUserID($Table, $ObjectID, $Field = Null) {
		if ($Field === Null) $Field = $Table.'ID';
		$SQL = Gdn::SQL();
		$UserID = $SQL
			->Select('InsertUserID')
			->From($Table)
			->Where($Field, (int)$ObjectID, False, False)
			->Get()
			->Value('InsertUserID');
		return $UserID; // NOTE: Gdn_DataSet.Value returns NULL, but should False as FirstRow()
	}
	
	public static function PutThank($Table, $ObjectID, $UserID, $Field = Null) {
		if ($Field === Null) $Field = $Table.'ID';
		$SQL = Gdn::SQL();
		$SQL
			->History(False, True)
			->Set($Field, $ObjectID)
			->Set('UserID', $UserID)
			->Insert('ThanksLog', array()); // BUG: https://github.com/vanillaforums/Garden/issues/566
		self::UpdateUserReceivedThankCount($UserID);
		$Function = 'Recalculate'.$Table.'ThankCount';
		call_user_func(array('self', $Function), $ObjectID);
	}
	
	public static function UpdateUserReceivedThankCount($UserID) {
		Gdn::SQL()
			->Update('User')
			->Set('ReceivedThankCount', 'ReceivedThankCount + 1', False)
			->Where('UserID', $UserID)
			->Put();
	}
	
	public static function RecalculateUserReceivedThankCount() {
		$SQL = Gdn::SQL();
		$SqlCount = $SQL
			->Select('*', 'count', 'Count')
			->From('ThanksLog t')
			->Where('t.UserID', 'u.UserID', False, False)
			->GetSelect();
		$SQL->Reset();
		$SQL
			->Update('User u')
			->Set('u.ReceivedThankCount', "($SqlCount)", False, False)
			->Put();
	}
	
	public static function RecalculateDiscussionThankCount($DiscussionID = False) {
		$SQL = Gdn::SQL();
		$Px = $SQL->Database->DatabasePrefix;
		$SqlCommentCount = $SQL
			->Select('*', 'count', 'Count')
			->From('ThanksLog t')
			->Where('t.DiscussionID', 'd.DiscussionID', False, False)
			->GetSelect();
		$SQL->Reset();
		if ($DiscussionID !== False) $SQL->Where('d.DiscussionID', $DiscussionID);
		$SQL
			->Update('Discussion d')
			->Set('d.ThankCount', "($SqlCommentCount)", False, False)
			->Put();
	}
	
	public static function RecalculateCommentThankCount($CommentID = False) {
		$SQL = Gdn::SQL();
		$Px = $SQL->Database->DatabasePrefix;
		$SqlCommentCount = $SQL
			->Select('*', 'count', 'Count')
			->From('ThanksLog t')
			->Where('t.CommentID', 'c.CommentID', False, False)
			->GetSelect();
		$SQL->Reset();
		if ($CommentID !== False) $SQL->Where('c.CommentID', $CommentID);
		$SQL
			->Update('Comment c')
			->Set('c.ThankCount', "($SqlCommentCount)", False, False)
			->Put();
	}
	
	public function GetDiscussionComments($DiscussionID, $CommentData, $Where = Null) {
		$Where['WithDiscussionID'] = $DiscussionID;
		$Result = $this->GetComments($CommentData, $Where);
		return $Result;
	}
	
	public function BaseQuery() {
		$this->SQL
			->Select('t.CommentID, t.DiscussionID, t.DateInserted, t.InsertUserID as UserID, u.Name') // TODO: Select photo?
			->Join('User u', 'u.UserID = t.InsertUserID', 'inner');
	}
	
	public function GetComments($CommentData, $Where = Null) {
		$Where['Comments'] = $CommentData;
		$this->BaseQuery();
		$Result = $this->Get($Where);
		return $Result;
	}
	
	public function GetReceivedThanks($UserID, $Where = Null) {
		$this->BaseQuery();
		$Where['ForUserID'] = $UserID;
		$ReceivedThanks = $this->Get($Where);
		$ThankData = array();
		$this->EventArguments['ReceivedThanks'] = $ReceivedThanks;
		$this->EventArguments['ThankData'] =& $ThankData;
		while($Data = $ReceivedThanks->NextRow()) {
			if ($Data->CommentID > 0) $ThankData['Comment'][$Data->CommentID][] = $Data;
			elseif ($Data->DiscussionID > 0) $ThankData['Discussion'][$Data->DiscussionID][] = $Data;
		}
		$this->FireEvent('BeforeRetreiveThankObjects');
		foreach (array_keys($ThankData) as $Name) {
			$ObjectIDs = array_keys($ThankData[$Name]);
			$ObjectPrimaryKey = $Name.'ID';
			switch ($Name) {
				case 'Comment': $this->SQL->Select('CommentID', "concat('/discussion/comment/', %s)", 'Url'); break;
				case 'Discussion': $this->SQL->Select('DiscussionID', "concat('/discussion/', %s)", 'Url'); break;
			}
			$this->EventArguments['ObjectPrimaryKey'] =& $ObjectPrimaryKey;
			$this->EventArguments['ObjectName'] =& $Name;
			$this->FireEvent('RetreiveThankObject');
			
			$Sql = $this->SQL
				->Select("'$Name'", '', 'Type')
				->Select($ObjectPrimaryKey, '', 'ObjectID')
				->Select('Body, 1, 255', 'mid', 'ExcerptText') // TODO: Config how many first chars get
				->Select('DateInserted')
				->From($Name)
				->WhereIn($ObjectPrimaryKey, $ObjectIDs)
				->GetSelect();
			$Sql = $this->SQL->ApplyParameters($Sql);
			$this->SQL->Reset();
			$SqlCollection[] = $Sql;
		}
		
		$this->EventArguments['SqlCollection'] =& $SqlCollection;
		$this->FireEvent('AfterRetreiveThankObjects');
		
		$ResultSql = implode("\n union \n", $SqlCollection);
		$Objects = $this->SQL->Query("select * from (\n$ResultSql\n) as tm order by DateInserted desc")->Result();
		$Result = array($ThankData, $Objects);
		return $Result;
	}
	
	public function Get($Where = False, $Offset = False, $Limit = False) {
		$bCountQuery = GetValue('bCountQuery', $Where, False, True);
		if ($bCountQuery) {
			$this->SQL->Select('*', 'count', 'RowCount');
			$Offset = $Limit = False;
		}
		if ($CommentData = GetValue('Comments', $Where, False, True)) {
			if ($CommentData instanceof Gdn_DataSet) $CommentData = ConsolidateArrayValuesByKey($CommentData->Result(), 'CommentID');
			if (!is_array($CommentData)) trigger_error('Unexpected type: '.gettype($CommentData), E_USER_ERROR);
			$this->SQL
				->WhereIn('t.CommentID', $CommentData);
		}
		if ($WithDiscussionID = GetValue('WithDiscussionID', $Where, False, True)) {
			$this->SQL->OrWhere('t.DiscussionID', $WithDiscussionID);
		}
		
		if ($ForUserID = GetValue('ForUserID', $Where, False, True)) {
			$this->SQL->Where('t.UserID', $ForUserID);
		}
		
		// Final where and return dataset or row count
		if (is_array($Where)) $this->SQL->Where($Where);
		$Result = $this->SQL
			->From('ThanksLog t')
			->Limit($Limit, $Offset)
			->Get();
		if ($bCountQuery) $Result = $Result->FirstRow()->RowCount;

		return $Result;
	}

	
}