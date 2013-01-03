<?
if(!isset($_GET['type']) || !is_number($_GET['type']) || $_GET['type'] > 3) {
	error(0);
}

$Options = array('v0','v2','320');
$Encodings = array('V0 (VBR)', 'V2 (VBR)', '320');
$EncodingKeys = array_fill_keys($Encodings, true);

if ($_GET['type'] == 3) {
	$List = "!(v0 | v2 | 320)";
} else {
	$List = '!'.$Options[$_GET['type']];
	if($_GET['type'] == 0) {
		$_GET['type'] = '0';
	} else {
		$_GET['type'] = display_str($_GET['type']);
	}
}
$SphQL = new SphinxQL_Query();
$SphQL->select('id, groupid')
	->from('better_transcode')
	->where('logscore', 100)
	->where_match('FLAC', 'format')
	->where_match($List, 'encoding', false)
	->order_by('RAND()')
	->limit(0, TORRENTS_PER_PAGE, TORRENTS_PER_PAGE);
if(!empty($_GET['search'])) {
	$SphQL->where_match($_GET['search'], '(groupname,artistname,year,taglist)');
}

$SphQLResult = $SphQL->query();
$TorrentCount = $SphQLResult->get_meta('total');

if ($TorrentCount == 0) {
	error('No results found!');
}

$Results = $SphQLResult->to_array('groupid');
$Groups = Torrents::get_groups(array_keys($Results));
$Groups = $Groups['matches'];

$Debug->log_var(true, 'Excluding '.$Encodings[$_GET['type']]);
$TorrentGroups = array();
foreach ($Groups as $GroupID => $Group) {
	if (empty($Group['Torrents'])) {
		unset($Groups[$GroupID]);
		continue;
	}
	foreach ($Group['Torrents'] as $Torrent) {
		$TorRemIdent = "$Torrent[Media] $Torrent[RemasterYear] $Torrent[RemasterTitle] $Torrent[RemasterRecordLabel] $Torrent[RemasterCatalogueNumber]";
		if (!isset($TorrentGroups[$Group['ID']])) {
			$TorrentGroups[$Group['ID']] = array(
				$TorRemIdent => array(
					'FlacID' => 0,
					'Formats' => array(),
					'RemasterTitle' => $Torrent['RemasterTitle'],
					'RemasterYear' => $Torrent['RemasterYear'],
					'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
					'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
					'IsSnatched' => false
				)
			);
		} elseif (!isset($TorrentGroups[$Group['ID']][$TorRemIdent])) {
			$TorrentGroups[$Group['ID']][$TorRemIdent] = array(
				'FlacID' => 0,
				'Formats' => array(),
				'RemasterTitle' => $Torrent['RemasterTitle'],
				'RemasterYear' => $Torrent['RemasterYear'],
				'RemasterRecordLabel' => $Torrent['RemasterRecordLabel'],
				'RemasterCatalogueNumber' => $Torrent['RemasterCatalogueNumber'],
				'IsSnatched' => false
			);
		}
		if (isset($EncodingKeys[$Torrent['Encoding']])) {
			$TorrentGroups[$Group['ID']][$TorRemIdent]['Formats'][$Torrent['Encoding']] = true;
		} elseif ($TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] == 0 && $Torrent['Format'] == 'FLAC' && $Torrent['LogScore'] == 100) {
			$TorrentGroups[$Group['ID']][$TorRemIdent]['FlacID'] = $Torrent['ID'];
			$TorrentGroups[$Group['ID']][$TorRemIdent]['IsSnatched'] = $Torrent['IsSnatched'];
		}
	}
}
$Debug->log_var($TorrentGroups, 'Torrent groups');

View::show_header('Transcode Search');
?>
<br />
<div class="thin">
	<form class="search_form" name="transcodes" action="" method="get">
		<table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
			<tr>
				<td class="label"><strong>Search:</strong></td>
				<td>
					<input type="hidden" name="method" value="transcode" />
					<input type="hidden" name="type" value="<?=$_GET['type']?>" />
					<input type="text" name="search" size="60" value="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : '')?>" />
					&nbsp;
					<input type="submit" value="Search" />
				</td>
			</tr>
		</table>
	</form>
	<table width="100%" class="torrent_table">
		<tr class="colhead">
			<td>Torrent</td>
			<td>V2</td>
			<td>V0</td>
			<td>320</td>
		</tr>
<?
foreach ($TorrentGroups as $GroupID => $Editions) {
	$GroupInfo = $Groups[$GroupID];
	$GroupYear = $GroupInfo['Year'];
	$ExtendedArtists = $GroupInfo['ExtendedArtists'];
	$GroupCatalogueNumber = $GroupInfo['CatalogueNumber'];
	$GroupName = $GroupInfo['Name'];
	$GroupRecordLabel = $GroupInfo['RecordLabel'];
	$ReleaseType = $GroupInfo['ReleaseType'];

	if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
		unset($ExtendedArtists[2]);
		unset($ExtendedArtists[3]);
		$ArtistNames = Artists::display_artists($ExtendedArtists);
	} else {
		$ArtistNames = '';
	}

	$TagList = array();
	$TagList = explode(' ',str_replace('_','.',$GroupInfo['TagList']));
	$TorrentTags = array();
	foreach ($TagList as $Tag) {
		$TorrentTags[] = '<a href="torrents.php?taglist='.$Tag.'">'.$Tag.'</a>';
	}
	$TorrentTags = implode(', ', $TorrentTags);
	foreach ($Editions as $RemIdent => $Edition) {
		if (!$Edition['FlacID']
				|| !empty($Edition['Formats']) && $_GET['type'] == 3
				|| $Edition['Formats'][$Encodings[$_GET['type']]] == true) {
			$Debug->log_var($Edition, 'Skipping '.$RemIdent);
			continue;
		}
		$DisplayName = $ArtistNames . '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$Edition['FlacID'].'#torrent'.$Edition['FlacID'].'" title="View Torrent">'.$GroupName.'</a>';
		if($GroupYear > 0) {
			$DisplayName .= " [".$GroupYear."]";
		}
		if ($ReleaseType > 0) {
			$DisplayName .= " [".$ReleaseTypes[$ReleaseType]."]";
		}
		if ($Edition['IsSnatched']) {
			$DisplayName .= ' ' . Format::torrent_label('Snatched!');
		}

		$EditionInfo = array();
		if (!empty($Edition['RemasterYear'])) {
			$ExtraInfo = $Edition['RemasterYear'];
		} else {
			$ExtraInfo = '';
		}
		if (!empty($Edition['RemasterRecordLabel'])) {
			$EditionInfo[] = $Edition['RemasterRecordLabel'];
		}
		if (!empty($Edition['RemasterTitle'])) {
			$EditionInfo[] = $Edition['RemasterTitle'];
		}
		if (!empty($Edition['RemasterCatalogueNumber'])) {
			$EditionInfo[] = $Edition['RemasterCatalogueNumber'];
		}
		if (!empty($Edition['RemasterYear'])) {
			$ExtraInfo .= ' - ';
		}
		$ExtraInfo .= implode(' / ', $EditionInfo);
?>
		<tr<?=$Edition['IsSnatched'] ? ' class="snatched_torrent"' : ''?>>
			<td>
				<span class="torrent_links_block">
					[ <a href="torrents.php?action=download&amp;id=<?=$Edition['FlacID']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">DL</a> ]
				</span>
				<?=$DisplayName?>
				<div class="torrent_info"><?=$ExtraInfo?></div>
				<div class="tags"><?=$TorrentTags?></div>
			</td>
			<td><strong><?=isset($Edition['Formats']['V2 (VBR)'])?'<span class="important_text_alt">YES</span>':'<span class="important_text">NO</span>'?></strong></td>
			<td><strong><?=isset($Edition['Formats']['V0 (VBR)'])?'<span class="important_text_alt">YES</span>':'<span class="important_text">NO</span>'?></strong></td>
			<td><strong><?=isset($Edition['Formats']['320'])?'<span class="important_text_alt">YES</span>':'<span class="important_text">NO</span>'?></strong></td>
		</tr>
<?
		}
	}
?>
	</table>
</div>
<?
View::show_footer();
?>
