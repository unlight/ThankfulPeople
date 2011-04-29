<?php if (!defined('APPLICATION')) exit();

$PluginInfo['ThankfulPeople'] = array(
	'Name' => 'Thankful People',
	//'Index' => 'ThankfulPeople', // used in Plugin::MakeMetaKey()
	'Description' => 'Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy).',
	'Version' => '2.0.1',
	'Date' => '27 Apr 2011',
	'Author' => 'Jerl Liandri',
	'AuthorUrl' => 'http://www.liandri-mining-corporation.com',
	'RequiredApplications' => array('Vanilla' => '>=2.1.0'),
	'RequiredTheme' => False, 
	'RequiredPlugins' => False,
	'RegisterPermissions' => array('Plugins.ThankfulPeople.Thank'),
	//'SettingsPermission' => False,
	'License' => 'X.Net License'
);

// TODO: PERMISSION THANK FOR CATEGORY

class ThankfulPeoplePlugin extends Gdn_Plugin {
	
	public function PluginController_ThankFor_Create($Sender) {
		$Session = Gdn::Session();
		$Sender->Permission('Plugins.ThankfulPeople.Thank'); // TODO: PERMISSION THANK FOR CATEGORY
		$Type = GetValue(0, $Sender->RequestArgs);
		$ObjectID = GetValue(1, $Sender->RequestArgs);
		$Table = ucfirst($Type);
		$Field = $Table.'ID';
		switch ($Field) {
			case 'CommentID':
			case 'DiscussionID': break;
			default: throw new Exception('Doh. Unknown...');
		}
		$SQL = Gdn::SQL();
		$UserID = $SQL
			->Select('InsertUserID')
			->From($Table)
			->Where($Field, (int)$ObjectID, False, False)
			->Get()
			->Value('InsertUserID');
		// NOTE: Gdn_DataSet.Value returns NULL, but should False as FirstRow()
		if ($UserID === Null) throw new Exception('Object has no owner.');
		$SQL
			->History(False, True)
			->Set($Field, $ObjectID)
			->Set('UserID', $UserID)
			->Insert('ThanksLog', array()); // BUG: https://github.com/vanillaforums/Garden/issues/566
		if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
			$Target = GetIncomingValue('Target', 'discussions');
			Redirect($Target);
		}
		// Json.
		
	}
	
	protected static function GetThankUrl($Sender) {
		$EventArguments =& $Sender->EventArguments;
		$Type = $EventArguments['Type'];
		$PropertyName = $Type.'ID';
		$Object = $EventArguments['Object'];
		$ObjectID = GetValue($PropertyName, $Object);
		return 'plugin/thankfor/'.strtolower($Type).'/'.$ObjectID.'?Target='.$Sender->SelfUrl;
	}
	
	public function DiscussionController_CommentOptions_Handler($Sender) {
		$Session = Gdn::Session();
		if (!$Session->IsValid()) return;
		static $LocalizedThankButtonText;
		if ($LocalizedThankButtonText === Null) $LocalizedThankButtonText = T('ThankCommentOption', T('Thanks'));
		$ThankUrl = self::GetThankUrl($Sender);
		$Option = '<span class="Thank">'.Anchor($LocalizedThankButtonText, $ThankUrl).'</span>';
		$Sender->Options .= $Option;
	}
	
	public function DiscussionController_AfterCommentBody_Handler($Sender) {
		$this->Structure(); // TEST
		die;
		$EventArguments =& $Sender->EventArguments;
		$Type = $EventArguments['Type'];
		//echo '<div class="ThankedBy"></div>';
	}
	
	public function Structure() {
		Gdn::Structure()
			->Table('Comment')
			->Column('CountThanks', 'smallint', 0)
			->Set();
		
/*		Gdn::Structure()
			->Table('User')
			->Column('CountThank', 'smallint', 0)
			->Column('CountThanked', 'smallint', 0)
			->Set();*/
		
		Gdn::Structure()
			->Table('ThanksLog')
			->Column('UserID', 'int', False, 'primary')
			->Column('CommentID', 'int', Null, 'primary')
			->Column('DiscussionID', 'int', Null, 'primary')
			->Column('DateInserted', 'datetime')
			->Column('InsertUserID', 'int')
			->Set();
	}
		
	public function Setup() {
	}
}