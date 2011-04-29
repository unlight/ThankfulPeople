<?php
require_once dirname(__FILE__).'/../../plugins/UsefulFunctions/bootstrap.console.php';

//call_user_func()

$SQL = Gdn::SQL();
$DiscussionDataSet = $SQL
	->Select('DiscussionID')
	->From('Discussion')
	->Limit(50)
	->OrderBy('DateLastComment', 'desc')
	->Get();
	
$MaxUserID = $SQL->Select('UserID', 'max', 'MaxUserID')->From('User')->Get()->FirstRow()->MaxUserID;

$CommentDataSet = $SQL
	->Select('CommentID, DiscussionID, InsertUserID')
	->From('Comment')
	->OrderBy('DateInserted', 'desc')
	->Limit(50)
	->Get();

foreach ($CommentDataSet as $Comment) {
	$Fields = array('CommentID' => $Comment->CommentID);
	$Fields['UserID'] = $Comment->InsertUserID;
	$Fields['InsertUserID'] = mt_rand(1, $MaxUserID);
	$Fields['DateInserted'] = Gdn_Format::ToDateTime();
	$SQL->Insert('ThanksLog', $Fields);
	Console::Message('Garbaged thank comment: %s', $Comment->CommentID);
}
	

ThanksLogModel::RecalculateUserReceivedThankCount();
ThanksLogModel::RecalculateCommentThankCount();
ThanksLogModel::RecalculateDiscussionThankCount();