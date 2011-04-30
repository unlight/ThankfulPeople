<?php if (!defined('APPLICATION')) exit();

$PluginInfo['ThankfulPeople'] = array(
	'Name' => 'Thankful People',
	//'Index' => 'ThankfulPeople', // used in Plugin::MakeMetaKey()
	'Description' => 'Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy).',
	'Version' => '2.0.2',
	'Date' => '29 Apr 2011',
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
	
	protected $ThankForComment = array(); // UserIDs array
	protected $CommentGroup = array();
	protected $DiscussionData = array();
	protected $Session;

	public function __construct() {
		$this->Session = Gdn::Session();
		//$this->Structure(); d('Structure'); // TEST
	}
	
	// TODO: _AttachMessageThankCount
/*   public function DiscussionController_AfterCommentMeta_Handler(&$Sender) {
		$this->AttachMessageThankCount($Sender);
	}
	
	protected function AttachMessageThankCount($Sender) {
		$ThankCount = mt_rand(1, 33);
		echo '<div class="ThankCount">'.Plural($Posts, 'Thanks: %s', 'Thanks: %s')), number_format($ThankCount, 0)).'</div>';
	}
	*/
	
	public function PluginController_ThankFor_Create($Sender) {
		$Session = $this->Session;
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
		// TODO: Check for duplicate
		// TODO: FireEvent here
		$UserID = ThanksLogModel::GetObjectInserUserID($Table, $ObjectID, $Field);
		if ($UserID === Null) throw new Exception('Object has no owner.');
		ThanksLogModel::PutThank($Table, $ObjectID, $UserID);
		if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
			$Target = GetIncomingValue('Target', 'discussions');
			Redirect($Target);
		}
		// TODO: JSON, DeliveryType BOOL
		
	}
	
	public function DiscussionController_Render_Before($Sender) {
		$ThanksLogModel = new ThanksLogModel();
		$DiscussionID = $Sender->DiscussionID;
		// TODO: Permission view thanked
		$CommentIDs = ConsolidateArrayValuesByKey($Sender->CommentData->Result(), 'CommentID');
		$DiscussionCommentThankDataSet = $ThanksLogModel->GetDiscussionComments($DiscussionID, $CommentIDs);
		// Consolidate.
		foreach ($DiscussionCommentThankDataSet as $ThankData) {
			$CommentID = $ThankData->CommentID;
			if ($CommentID > 0) {
				$this->CommentGroup[$CommentID][] = $ThankData;
				$this->ThankForComment[$CommentID][] = $ThankData->UserID;
			} elseif ($ThankData->DiscussionID > 0) {
				$this->DiscussionData[$ThankData->UserID] = $ThankData;
			}
		}
		
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
		//$Sender->AddJsFile('jquery.expander.js');
		// TODO: REMOVE WAITING FOR VANILLA 2.1.0
		$Sender->AddJsFile('plugins/ThankfulPeople/js/jquery.expander.js');
		$Sender->AddJsFile('plugins/ThankfulPeople/js/thankfulpeople.functions.js');
	}
	
	public function DiscussionController_CommentOptions_Handler($Sender) {
		$EventArguments =& $Sender->EventArguments;
		$Type = $EventArguments['Type'];
		$Object = $EventArguments['Object'];
		$Session = Gdn::Session();
		if (!$Session->IsValid() || $Object->InsertUserID == $Session->UserID) return;
		if ($Type == 'Discussion') {
			$DiscussionID = $ObjectID = $Object->DiscussionID;
			if (array_key_exists($Session->UserID, $this->DiscussionData)) return;
		}
		elseif ($Type == 'Comment') {
			$CommentID = $ObjectID = $Object->CommentID;
			if (array_key_exists($CommentID, $this->ThankForComment) && in_array($Session->UserID, $this->ThankForComment[$CommentID])) return;
		}
		
		static $LocalizedThankButtonText;
		if ($LocalizedThankButtonText === Null) $LocalizedThankButtonText = T('ThankCommentOption', T('Thanks'));
		
		$PropertyName = $Type.'ID';
		$ThankUrl = 'plugin/thankfor/'.strtolower($Type).'/'.$ObjectID.'?Target='.$Sender->SelfUrl;
		
		$Option = '<span class="Thank">'.Anchor($LocalizedThankButtonText, $ThankUrl).'</span>';
		$Sender->Options .= $Option;
	}
	
	public function DiscussionController_AfterCommentBody_Handler($Sender) {
		$Object = $Sender->EventArguments['Object'];
		$Type = $Sender->EventArguments['Type'];
		$ThankedByList = '';
		switch ($Type) {
			case 'Comment': {
				if ($Object->ThankCount <= 0) return;
				$ThankedByCollection = GetValue($Object->CommentID, $this->CommentGroup);
				$MessageThankCount = count($ThankedByCollection);
				if ($ThankedByCollection) $ThankedByList = self::ThankedByList($ThankedByCollection);
			} break;
			case 'Discussion': {
				if ($Object->ThankCount <= 0) return;
				$MessageThankCount = count($this->DiscussionData);
				$ThankedByList = self::ThankedByList($this->DiscussionData);
			} break;
			default: throw new Exception('What...');
		}
		if ($ThankedByList != '') {
			//echo '<div class="ThankedByBox"><span class="ThankedBy">'.T('Thanked by').'</span>'.$ThankedByList.'</div>';
			$LocalizedPluralText = Plural($MessageThankCount, 'Thanked by (%1$d)', 'Thanked by (%1$d)');
			//echo '<div class="ThankedByBox"><span class="ThankedBy">'.sprintf(T('Thanked by (%1$d)'), $MessageThankCount).'</span>'.$ThankedByList.'</div>';
			echo '<div class="ThankedByBox"><span class="ThankedBy">'.$LocalizedPluralText.'</span>'.$ThankedByList.'</div>';
		}
	}
	
	public static function ThankedByList($ThankedByCollection) {
		$ThankedByList = implode(' ', array_map('UserAnchor', $ThankedByCollection));
		return $ThankedByList;
	}
	
	public function UserInfoModule_OnBasicInfo_Handler($Sender) {
		echo Wrap(T('UserInfoModule.Thanked'), 'dt', array('class' => 'ReceivedThankCount'));
		echo Wrap($Sender->User->ReceivedThankCount, 'dd', array('class' => 'ReceivedThankCount'));
	}
	
	public function ProfileController_Render_Before($Sender) {
		if (!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) return;
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
	}
	
	public function ProfileController_AddProfileTabs_Handler($Sender) {
		$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
		$ReceivedThankCount = $Sender->User->ReceivedThankCount;
		$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
		$Sender->AddProfileTab($Thanked, 'profile/receivedthanks/'.$UserReference.'/'.$Username, 'Thanked');
	}
	
	public function ProfileController_ReceivedThanks_Create($Sender) {
		$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
		$Sender->GetUserInfo($UserReference, $Username);
		$View = $this->GetView('receivedthanks.php');
		
		$ReceivedThankCount = $ReceivedThankCount = $Sender->User->ReceivedThankCount;
		$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
		
		$Sender->SetTabView($Thanked, $View);
		$ViewingUserID = GetValue(0, $Sender->RequestArgs);
		$ThanksLogModel = new ThanksLogModel();
		list($Sender->ThankData, $Sender->ThankObjects) = $ThanksLogModel->GetReceivedThanks($ViewingUserID);

		$Sender->Render();
	}
	
	public function Structure($Drop = False) {
		Gdn::Structure()
			->Table('Comment')
			->Column('ThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('Discussion')
			->Column('ThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('User')
			//->Column('ThankCount', 'usmallint', 0)
			->Column('ReceivedThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('ThanksLog')
			->Column('UserID', 'umediumint', False, 'key')
			->Column('CommentID', 'umediumint', Null)
			->Column('DiscussionID', 'umediumint', Null)
			->Column('DateInserted', 'datetime')
			->Column('InsertUserID', 'umediumint', False, 'key')
			->Engine('MyISAM')
			->Set(False, $Drop);
			
		ThanksLogModel::RecalculateUserReceivedThankCount();
		ThanksLogModel::RecalculateCommentThankCount();
		ThanksLogModel::RecalculateDiscussionThankCount();
	}
		
	public function Setup() {
	}
}