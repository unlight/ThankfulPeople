<?php
require_once dirname(__FILE__).'/../../plugins/UsefulFunctions/bootstrap.console.php';

$Argument = GetValue(1, $argv);
$SQL = Gdn::SQL();
$MaxUserID = $SQL->Select('UserID', 'max', 'MaxUserID')->From('User')->Get()->FirstRow()->MaxUserID;

if ($Argument == 'structure') {
	$ThankfulPeoplePlugin = new ThankfulPeoplePlugin();
	$Drop = Console::Argument('drop') !== False;
	$ThankfulPeoplePlugin->Structure($Drop);
}
elseif ($Argument == 'calc') {
	ThanksLogModel::RecalculateUserReceivedThankCount();
	//ThanksLogModel::RecalculateCommentThankCount();
	//ThanksLogModel::RecalculateDiscussionThankCount();
} elseif ($Argument == 'garbage') {
	$Limit = Console::Argument('limit');
	if (!$Limit) $Limit = 10;
	$CommentDataSet = $SQL
		->Select('CommentID, DiscussionID, InsertUserID')
		->From('Comment')
		->OrderBy('DateInserted', 'desc')
		->Limit($Limit)
		->Get();
	$Loop = Console::Argument('loop');
	if (!is_numeric($Loop) || $Loop <= 0) $Loop = 1;
	for ($i = 0; $i < $Loop; $i++) {
		foreach ($CommentDataSet as $Comment) {
			$InsertUserID = mt_rand(1, $MaxUserID);
			$Fields = array('CommentID' => $Comment->CommentID);
			$Fields['UserID'] = $Comment->InsertUserID;
			$Fields['InsertUserID'] = $InsertUserID;
			$Fields['DateInserted'] = Gdn_Format::ToDateTime();
			
			if ($InsertUserID % 5 == 0) {
				unset($Fields['CommentID']);
				$Fields['DiscussionID'] = $Comment->DiscussionID;
			}
			
			$SQL->Insert('ThanksLog', $Fields);
			Console::Message('Garbaged thank comment: %s', GetValue('CommentID', $Fields));
		}
	}
}

/* LUM_ThankfulPeople
	CommentID
	UserID
*/
	

