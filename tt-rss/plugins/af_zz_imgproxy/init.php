<?php
class Af_Zz_ImgProxy extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Load insecure images via built-in proxy",
			"fox");
	}

	private $ssl_known_whitelist = "imgur.com gfycat.com i.reddituploads.com pbs.twimg.com i.redd.it i.sli.mg media.tumblr.com";

	function is_public_method($method) {
		return $method === "imgproxy";
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);
		$host->add_hook($host::HOOK_ENCLOSURE_ENTRY, $this);

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_enclosure_entry($enc) {
		if (preg_match("/image/", $enc["content_type"])) {
			$proxy_all = $this->host->get($this, "proxy_all");

			$enc["content_url"] = $this->rewrite_url_if_needed($enc["content_url"], $proxy_all);
		}

		return $enc;
	}

	function hook_render_article($article) {
		return $this->hook_render_article_cdm($article);
	}

	public function imgproxy() {

		$url = rewrite_relative_url(get_self_url_prefix(), $_REQUEST["url"]);

		// called without user context, let's just redirect to original URL
		if (!$_SESSION["uid"]) {
			header("Location: $url");
			return;
		}

		$local_filename = CACHE_DIR . "/images/" . sha1($url);

		if ($_REQUEST["debug"] == "1") { print $url . "\n" . $local_filename; die; }

		header("Content-Disposition: inline; filename=\"".basename($local_filename)."\"");

		if (file_exists($local_filename)) {

			send_local_file($local_filename);

		} else {
			$data = fetch_file_contents(["url" => $url, "max_size" => MAX_CACHE_FILE_SIZE]);

			if ($data) {

				$disable_cache = $this->host->get($this, "disable_cache");

				if (!$disable_cache && strlen($data) > MIN_CACHE_FILE_SIZE) {
					if (file_put_contents($local_filename, $data)) {
						$mimetype = mime_content_type($local_filename);
						header("Content-type: $mimetype");
					}
				}

				print $data;
			} else {
				global $fetch_last_error;
				global $fetch_last_error_code;
				global $fetch_last_error_content;

				if (function_exists("imagecreate") && !isset($_REQUEST["text"])) {
					$img = imagecreate(450, 75);

					/*$bg =*/ imagecolorallocate($img, 255, 255, 255);
					$textcolor = imagecolorallocate($img, 255, 0, 0);

					imagerectangle($img, 0, 0, 450-1, 75-1, $textcolor);

					imagestring($img, 5, 5, 5, "Proxy request failed", $textcolor);
					imagestring($img, 5, 5, 30, truncate_middle($url, 46, "..."), $textcolor);
					imagestring($img, 5, 5, 55, "HTTP Code: $fetch_last_error_code", $textcolor);

					header("Content-type: image/png");
					print imagepng($img);
					imagedestroy($img);

				} else {
					header("Content-type: text/html");

					http_response_code(400);

					print "<h1>Proxy request failed.</h1>";
					print "<p>Fetch error $fetch_last_error ($fetch_last_error_code)</p>";
					print "<p>URL: $url</p>";
					print "<textarea cols='80' rows='25'>" . htmlspecialchars($fetch_last_error_content) . "</textarea>";
				}
			}
		}
	}

	function rewrite_url_if_needed($url, $all_remote = false) {
		$scheme = parse_url($url, PHP_URL_SCHEME);

		if ($all_remote) {
			$host = parse_url($url, PHP_URL_HOST);
			$self_host = parse_url(get_self_url_prefix(), PHP_URL_HOST);

			$is_remote = $host != $self_host;
		} else {
			$is_remote = false;
		}

		if (($scheme != 'https' && $scheme != "") || $is_remote) {
			if (strpos($url, "data:") !== 0) {
				$parts = parse_url($url);

				foreach (explode(" " , $this->ssl_known_whitelist) as $host) {
					if (substr(strtolower($parts['host']), -strlen($host)) === strtolower($host)) {
						$parts['scheme'] = 'https';
						$url = build_url($parts);
						if ($all_remote && $is_remote) {
							break;
						} else {
							return $url;
						}
					}
				}

				return get_self_url_prefix() . "/public.php?op=pluginhandler&plugin=af_zz_imgproxy&pmethod=imgproxy&url=" .
					urlencode($url);
			}
		}

		return $url;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function hook_render_article_cdm($article, $api_mode = false) {

		$need_saving = false;
		$proxy_all = $this->host->get($this, "proxy_all");

		$doc = new DOMDocument();
		if (@$doc->loadHTML($article["content"])) {
			$xpath = new DOMXPath($doc);
			$imgs = $xpath->query("//img[@src]");

			foreach ($imgs as $img) {
				$new_src = $this->rewrite_url_if_needed($img->getAttribute("src"), $proxy_all);

				if ($new_src != $img->getAttribute("src")) {
					$img->setAttribute("src", $new_src);
					$img->removeAttribute("srcset");

					$need_saving = true;
				}
			}

			$vids = $xpath->query("//video");

			foreach ($vids as $vid) {
				if ($vid->hasAttribute("poster")) {
					$new_src = $this->rewrite_url_if_needed($vid->getAttribute("poster"), $proxy_all);

					if ($new_src != $vid->getAttribute("poster")) {
						$vid->setAttribute("poster", $new_src);

						$need_saving = true;
					}
				}

				$vsrcs = $xpath->query("source", $vid);

				foreach ($vsrcs as $vsrc) {
					$new_src = $this->rewrite_url_if_needed($vsrc->getAttribute("src"), $proxy_all);

					if ($new_src != $vsrc->getAttribute("src")) {
						$vid->setAttribute("src", $new_src);

						$need_saving = true;
					}
				}
			}
		}

		if ($need_saving) $article["content"] = $doc->saveHTML();

		return $article;
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>extension</i> ".__('Image proxy settings (af_zz_imgproxy)')."\">";

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.getValues()));
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						Notify.info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print_hidden("op", "pluginhandler");
		print_hidden("method", "save");
		print_hidden("plugin", "af_zz_imgproxy");

		$proxy_all = $this->host->get($this, "proxy_all");
		print_checkbox("proxy_all", $proxy_all);
		print "&nbsp;<label for=\"proxy_all\">" . __("Enable proxy for all remote images.") . "</label><br/>";

		$disable_cache = $this->host->get($this, "disable_cache");
		print_checkbox("disable_cache", $disable_cache);
		print "&nbsp;<label for=\"disable_cache\">" . __("Don't cache files locally.") . "</label>";

		print "<p>"; print_button("submit", __("Save"));

		print "</form>";

		print "</div>";
	}

	function save() {
		$proxy_all = checkbox_to_sql_bool($_POST["proxy_all"]);
		$disable_cache = checkbox_to_sql_bool($_POST["disable_cache"]);

		$this->host->set($this, "proxy_all", $proxy_all, false);
		$this->host->set($this, "disable_cache", $disable_cache);

		echo __("Configuration saved");
	}

	function api_version() {
		return 2;
	}
}
