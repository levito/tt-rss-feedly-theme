<?php
// Compatibility shim for hooking up jimIcon to Tiny Tiny RSS.

require_once "jimIcon.php";

class floIconIcon {
        function getImageResource() {
                return $this->img;
        }
}

class floIcon {
        function readICO($file) {
                $jim = new jimIcon();
                $icon = new floIconIcon();
                $icon->img = $jim->fromiconstring(file_get_contents($file));
                $this->images = array($icon);
        }
}
?>
