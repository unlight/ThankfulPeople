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
	ThanksLogModel::RecalculateCommentThankCount();
	ThanksLogModel::RecalculateDiscussionThankCount();
} elseif ($Argument == 'com') {
	$Limit = Console::Argument('limit');
	if (!$Limit) $Limit = 10;
	$CommentDataSet = $SQL
		->Select('CommentID, DiscussionID, InsertUserID')
		->From('Comment')
		->OrderBy('DateInserted', 'desc')
		->Limit($Limit)
		->Get();
	foreach ($CommentDataSet as $Comment) {
		$Fields = array('CommentID' => $Comment->CommentID);
		$Fields['UserID'] = $Comment->InsertUserID;
		$Fields['InsertUserID'] = mt_rand(1, $MaxUserID);
		$Fields['DateInserted'] = Gdn_Format::ToDateTime();
		$SQL->Insert('ThanksLog', $Fields);
		Console::Message('Garbaged thank comment: %s', $Comment->CommentID);
	}
}

