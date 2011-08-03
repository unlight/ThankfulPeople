<?php if (!defined('APPLICATION')) exit();

class ThanksLogModel extends Gdn_Model {
	
	protected static $TableFields = array(
		'comment' => 'CommentID',
		'discussion' => 'DiscussionID'
	);
	
	protected static $TableNames = array();
	
	public function __construct() {
		parent::__construct('ThanksLog');
		$this->FireEvent('AfterConstruct');
	}
	
	public function GetCount($Where = False) {
		$Where['bCountQuery'] = True;
		$Result = $this->Get($Where);
		return $Result;
	}
	
	public function Get($Where = False, $Offset = False, $Limit = False) {
		
		$bCountQuery = GetValue('bCountQuery', $Where, False, True);
		$this->EventArguments['WhereOptions'] = $Where;
		$this->EventArguments['bCountQuery'] = $bCountQuery;
		
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
		
		$this->FireEvent('BeforeGet');
		
		// Final where and return dataset or row count
		if (is_array($Where)) $this->SQL->Where($Where);
		$Result = $this->SQL
			->From('ThanksLog t')
			->Limit($Limit, $Offset)
			->Get();
		if ($bCountQuery) $Result = $Result->FirstRow()->RowCount;
		return $Result;
	}
	
	public static function GetPrimaryKeyField($Name) { // Type, Table name
		$Name = strtolower($Name);
		if (array_key_exists($Name, self::$TableFields)) return self::$TableFields[$Name];
		return self::GetTableName($Name).'ID';
	}
	
	public static function GetTableName($Name) {
		$Name = strtolower($Name);
		return ArrayValue($Name, self::$TableNames, ucfirst($Name));
	}
	
	public static function GetObjectInserUserID($Name, $ObjectID) {
		$Field = self::GetPrimaryKeyField($Name);
		$Table = self::GetTableName($Name);
		$UserID = Gdn::SQL()
			->Select('InsertUserID')
			->From($Table)
			->Where($Field, (int)$ObjectID, False, False)
			->Get()
			->Value('InsertUserID');
		return $UserID; // NOTE: Gdn_DataSet.Value returns NULL, but should False as FirstRow()
	}
	
	public static function RemoveThank($Type, $ObjectID, $SessionUserID) {
		$Field = self::GetPrimaryKeyField($Type);
		$UserID = self::GetObjectInserUserID($Type, $ObjectID);
		$SQL = Gdn::SQL();
		$SQL
			->Where($Field, $ObjectID)
			->Where('InsertUserID', $SessionUserID)
			->Limit(1)
			->Delete('ThanksLog');
		self::UpdateUserReceivedThankCount($UserID, '-1');
	}
	
	public static function PutThank($Type, $ObjectID, $UserID) {
		$Field = self::GetPrimaryKeyField($Type);
		$SQL = Gdn::SQL();
		$SQL
			->History(False, True)
			->Set($Field, $ObjectID)
			->Set('UserID', $UserID)
			->Insert('ThanksLog', array()); // BUG: https://github.com/vanillaforums/Garden/issues/566
		self::UpdateUserReceivedThankCount($UserID);
		//$Function = 'Recalculate'.$Type.'ThankCount';
		//call_user_func(array('self', $Function), $ObjectID);
	}
	
	public static function UpdateUserReceivedThankCount($UserID, $Value = '+1') {
		if (!in_array($Value, array('-1', '+1'))) $Value = '+1';
		Gdn::SQL()
			->Update('User')
			->Set('ReceivedThankCount', 'ReceivedThankCount' . $Value, False)
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
	
	public function GetThankfulPeople($Type, $ObjectID) {
		$this->BaseQuery();
		$Field = self::GetPrimaryKeyField($Type);
		$Result = $this->Get(array($Field => $ObjectID));
		return $Result;
	}
	
	public function GetComments($CommentData, $Where = Null) {
		$Where['Comments'] = $CommentData;
		$this->BaseQuery();
		$Result = $this->Get($Where);
		return $Result;
	}
	
	public function GetReceivedThanks($Where = False, $Offset = False, $Limit = False) {
		$this->BaseQuery();
		$this->SQL
			->OrderBy('t.DateInserted', 'desc');
		$ReceivedThanks = $this->Get($Where, $Offset, $Limit);
		$ThankData = array();
		$this->EventArguments['ReceivedThanks'] = $ReceivedThanks;
		$this->EventArguments['ThankData'] =& $ThankData;
		while($Data = $ReceivedThanks->NextRow()) {
			if ($Data->CommentID > 0) $ThankData['Comment'][$Data->CommentID][] = $Data;
			elseif ($Data->DiscussionID > 0) $ThankData['Discussion'][$Data->DiscussionID][] = $Data;
		}
		$this->FireEvent('BeforeRetreiveThankObjects');
		if (count($ThankData) == 0) return array(array(), array());
		foreach (array_keys($ThankData) as $Type) {
			$ObjectIDs = array_keys($ThankData[$Type]);
			$ObjectPrimaryKey = self::GetPrimaryKeyField($Type);
			$Table = self::GetTableName($Type);
			$ExcerptTextField = 'Body';
			switch ($Table) {
				case 'Comment': $this->SQL->Select('CommentID', "concat('discussion/comment/', %s)", 'Url'); break;
				case 'Discussion': $this->SQL->Select('DiscussionID', "concat('discussion/', %s)", 'Url'); break;
			}
			$this->EventArguments['ObjectPrimaryKey'] =& $ObjectPrimaryKey;
			$this->EventArguments['ObjectTable'] =& $Table;
			$this->EventArguments['ExcerptTextField'] =& $ExcerptTextField;
			$this->FireEvent('RetreiveThankObject');
			
			$ObjectIDs = implode(',', array_map('intval', $ObjectIDs)); // TODO: REMOVE
			
			$Sql = $this->SQL
				->Select("'$Type'", '', 'Type')
				->Select($ObjectPrimaryKey, '', 'ObjectID')
				->Select($ExcerptTextField, 'mid(%s, 1, 255)', 'ExcerptText') // TODO: Config how many first chars get
				->Select('DateInserted')
				->From($Table)
				->Where($ObjectPrimaryKey .' in ('.$ObjectIDs.')', Null, False, False)
				->GetSelect();
			//$Sql = $this->SQL->ApplyParameters($Sql); // TODO: WAITING FOR APPLYING COMMITS
			$this->SQL->Reset();
			$SqlCollection[] = $Sql;
		}
		
		$this->EventArguments['SqlCollection'] =& $SqlCollection;
		$this->FireEvent('AfterRetreiveThankObjects');
		
		$ResultSql = implode("\n union \n", $SqlCollection);
		$Objects = $this->SQL->Query("select * from (\n$ResultSql\n) as t order by DateInserted desc")->Result();
		$Result = array($ThankData, $Objects);
		return $Result;
	}
	
	public static function CleanUp() {
		$SQL = Gdn::SQL();
		$Px = $SQL->Database->DatabasePrefix;
		$SQL->Query("delete t.* from {$Px}ThanksLog t 
			left join {$Px}Comment c on c.CommentID = t.CommentID 
			where c.commentID is null and t.commentID > 0");
		$SQL->Query("delete t.* from {$Px}ThanksLog t 
			left join {$Px}Discussion d on d.DiscussionID = t.DiscussionID 
			where d.DiscussionID is null and t.DiscussionID > 0");
	}
	
}