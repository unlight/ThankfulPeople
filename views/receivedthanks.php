<?php if (!defined('APPLICATION')) exit();

/*	<a class="Title" href="/discussion/2168">Sed egestas</a>			
	<div class="Excerpt"><a href="/discussion/2168">Sed egestas</a></div>
		
	<div class="Meta">
		<span><?php echo Gdn_Format::Date($Object->DateInserted);?></span>
		<!--<span><a href="/discussion/2168">?</a></span> -->
	</div>
*/

?>

<ul class="DataList SearchResults ThankObjects">
<?php foreach($this->ThankObjects as $Object) {
	$ThankCollection = GetValue($Object->ObjectID, GetValue($Object->Type, $this->ThankData));
	$ExcerptText = SliceString(Gdn_Format::Text($Object->ExcerptText), 200);
	if ($Object->Url) $ExcerptText = Anchor($ExcerptText, $Object->Url);
	// TODO: thank DateInserted
?>
<li class="Item">
	<div class="ItemContent">
		<div class="Excerpt"><?php echo $ExcerptText;?></div>
	<?php echo ThankfulPeoplePlugin::ThankedByBox($ThankCollection); ?>
	</div>
</li>
	
<?php } ?>

</ul>


