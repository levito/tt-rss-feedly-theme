<?php

function print_select($id, $default, $values, $attributes = "", $name = "") {
	if (!$name) $name = $id;

	print "<select name=\"$name\" id=\"$id\" $attributes>";
	foreach ($values as $v) {
		if ($v == $default)
			$sel = "selected=\"1\"";
		else
			$sel = "";

		$v = trim($v);

		print "<option value=\"$v\" $sel>$v</option>";
	}
	print "</select>";
}

function print_select_hash($id, $default, $values, $attributes = "", $name = "") {
	if (!$name) $name = $id;

	print "<select name=\"$name\" id='$id' $attributes>";
	foreach (array_keys($values) as $v) {
		if ($v == $default)
			$sel = 'selected="selected"';
		else
			$sel = "";

		$v = trim($v);

		print "<option $sel value=\"$v\">".$values[$v]."</option>";
	}

	print "</select>";
}

function print_hidden($name, $value) {
	print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"$name\" value=\"$value\">";
}

function print_checkbox($id, $checked, $value = "", $attributes = "") {
	$checked_str = $checked ? "checked" : "";
	$value_str = $value ? "value=\"$value\"" : "";

	print "<input dojoType=\"dijit.form.CheckBox\" id=\"$id\" $value_str $checked_str $attributes name=\"$id\">";
}

function print_button($type, $value, $attributes = "") {
	print "<p><button dojoType=\"dijit.form.Button\" $attributes type=\"$type\">$value</button>";
}

function print_radio($id, $default, $true_is, $values, $attributes = "") {
	foreach ($values as $v) {

		if ($v == $default)
			$sel = "checked";
		else
			$sel = "";

		if ($v == $true_is) {
			$sel .= " value=\"1\"";
		} else {
			$sel .= " value=\"0\"";
		}

		print "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\"
				type=\"radio\" $sel $attributes name=\"$id\">&nbsp;$v&nbsp;";

	}
}

function print_feed_multi_select($id, $default_ids = [],
						   $attributes = "", $include_all_feeds = true,
						   $root_id = null, $nest_level = 0) {

	$pdo = DB::pdo();

	print_r(in_array("CAT:6",$default_ids));

	if (!$root_id) {
		print "<select multiple=\true\" id=\"$id\" name=\"$id\" $attributes>";
		if ($include_all_feeds) {
			$is_selected = (in_array("0", $default_ids)) ? "selected=\"1\"" : "";
			print "<option $is_selected value=\"0\">".__('All feeds')."</option>";
		}
	}

	if (get_pref('ENABLE_FEED_CATS')) {

		if (!$root_id) $root_id = null;

		$sth = $pdo->prepare("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = :uid AND
				(parent_cat = :root_id OR (:root_id IS NULL AND parent_cat IS NULL)) ORDER BY title");

		$sth->execute([":uid" => $_SESSION['uid'], ":root_id" => $root_id]);

		while ($line = $sth->fetch()) {

			for ($i = 0; $i < $nest_level; $i++)
				$line["title"] = " - " . $line["title"];

			$is_selected = in_array("CAT:".$line["id"], $default_ids) ? "selected=\"1\"" : "";

			printf("<option $is_selected value='CAT:%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));

			if ($line["num_children"] > 0)
				print_feed_multi_select($id, $default_ids, $attributes,
					$include_all_feeds, $line["id"], $nest_level+1);

			$f_sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
					WHERE cat_id = ? AND owner_uid = ? ORDER BY title");

			$f_sth->execute([$line['id'], $_SESSION['uid']]);

			while ($fline = $f_sth->fetch()) {
				$is_selected = (in_array($fline["id"], $default_ids)) ? "selected=\"1\"" : "";

				$fline["title"] = " + " . $fline["title"];

				for ($i = 0; $i < $nest_level; $i++)
					$fline["title"] = " - " . $fline["title"];

				printf("<option $is_selected value='%d'>%s</option>",
					$fline["id"], htmlspecialchars($fline["title"]));
			}
		}

		if (!$root_id) {
			$is_selected = in_array("CAT:0", $default_ids) ? "selected=\"1\"" : "";

			printf("<option $is_selected value='CAT:0'>%s</option>",
				__("Uncategorized"));

			$f_sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
					WHERE cat_id IS NULL AND owner_uid = ? ORDER BY title");
			$f_sth->execute([$_SESSION['uid']]);

			while ($fline = $f_sth->fetch()) {
				$is_selected = in_array($fline["id"], $default_ids) ? "selected=\"1\"" : "";

				$fline["title"] = " + " . $fline["title"];

				for ($i = 0; $i < $nest_level; $i++)
					$fline["title"] = " - " . $fline["title"];

				printf("<option $is_selected value='%d'>%s</option>",
					$fline["id"], htmlspecialchars($fline["title"]));
			}
		}

	} else {
		$sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
				WHERE owner_uid = ? ORDER BY title");
		$sth->execute([$_SESSION['uid']]);

		while ($line = $sth->fetch()) {

			$is_selected = (in_array($line["id"], $default_ids)) ? "selected=\"1\"" : "";

			printf("<option $is_selected value='%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));
		}
	}

	if (!$root_id) {
		print "</select>";
	}
}

function print_feed_cat_select($id, $default_id,
							   $attributes, $include_all_cats = true, $root_id = null, $nest_level = 0) {

	if (!$root_id) {
		print "<select id=\"$id\" name=\"$id\" default=\"$default_id\" $attributes>";
	}

	$pdo = DB::pdo();

	if (!$root_id) $root_id = null;

	$sth = $pdo->prepare("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = :uid AND
				  (parent_cat = :root_id OR (:root_id IS NULL AND parent_cat IS NULL)) ORDER BY title");
	$sth->execute([":uid" => $_SESSION['uid'], ":root_id" => $root_id]);

	$found = 0;

	while ($line = $sth->fetch()) {
		++$found;

		if ($line["id"] == $default_id) {
			$is_selected = "selected=\"1\"";
		} else {
			$is_selected = "";
		}

		for ($i = 0; $i < $nest_level; $i++)
			$line["title"] = " - " . $line["title"];

		if ($line["title"])
			printf("<option $is_selected value='%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));

		if ($line["num_children"] > 0)
			print_feed_cat_select($id, $default_id, $attributes,
				$include_all_cats, $line["id"], $nest_level+1);
	}

	if (!$root_id) {
		if ($include_all_cats) {
			if ($found > 0) {
				print "<option disabled=\"1\">--------</option>";
			}

			if ($default_id == 0) {
				$is_selected = "selected=\"1\"";
			} else {
				$is_selected = "";
			}

			print "<option $is_selected value=\"0\">".__('Uncategorized')."</option>";
		}
		print "</select>";
	}
}

function stylesheet_tag($filename, $id = false) {
	$timestamp = filemtime($filename);

	$id_part = $id ? "id=\"$id\"" : "";

	return "<link rel=\"stylesheet\" $id_part type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
}

function javascript_tag($filename) {
	$query = "";

	if (!(strpos($filename, "?") === FALSE)) {
		$query = substr($filename, strpos($filename, "?")+1);
		$filename = substr($filename, 0, strpos($filename, "?"));
	}

	$timestamp = filemtime($filename);

	if ($query) $timestamp .= "&$query";

	return "<script type=\"text/javascript\" charset=\"utf-8\" src=\"$filename?$timestamp\"></script>\n";
}

function format_warning($msg, $id = "") {
	return "<div class=\"alert\" id=\"$id\">$msg</div>";
}

function format_notice($msg, $id = "") {
	return "<div class=\"alert alert-info\" id=\"$id\">$msg</div>";
}

function format_error($msg, $id = "") {
	return "<div class=\"alert alert-danger\" id=\"$id\">$msg</div>";
}

function print_notice($msg) {
	return print format_notice($msg);
}

function print_warning($msg) {
	return print format_warning($msg);
}

function print_error($msg) {
	return print format_error($msg);
}

function format_inline_player($url, $ctype) {

	$entry = "";

	$url = htmlspecialchars($url);

	if (strpos($ctype, "audio/") === 0) {

		$entry .= "<div class='inline-player'>";

		if ($_SESSION["hasAudio"] && (strpos($ctype, "ogg") !== false ||
				$_SESSION["hasMp3"])) {

			$entry .= "<audio preload=\"none\" controls>
					<source type=\"$ctype\" src=\"$url\"/>
					</audio> ";

		}

		if ($entry) $entry .= "<a target=\"_blank\" rel=\"noopener noreferrer\"
				href=\"$url\">" . basename($url) . "</a>";

		$entry .= "</div>";

		return $entry;

	}

	return "";
}

function print_label_select($name, $value, $attributes = "") {

	$pdo = Db::pdo();

	$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2
			WHERE owner_uid = ? ORDER BY caption");
	$sth->execute([$_SESSION['uid']]);

	print "<select default=\"$value\" name=\"" . htmlspecialchars($name) .
		"\" $attributes>";

	while ($line = $sth->fetch()) {

		$issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

		print "<option value=\"".htmlspecialchars($line["caption"])."\"
				$issel>" . htmlspecialchars($line["caption"]) . "</option>";

	}

#		print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

	print "</select>";


}
