<?php
class Toggle_Sidebar extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Adds a main toolbar button to toggle sidebar",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_MAIN_TOOLBAR_BUTTON, $this);
	}

	function hook_main_toolbar_button() {
		?>

		<button dojoType="dijit.form.Button" onclick="Feeds.toggle()">
			<i class="material-icons"
               title="<?php echo __('Toggle feedlist') ?>">fullscreen</i>
		</button>

		<?php
	}

	function api_version() {
		return 2;
	}

}
?>
