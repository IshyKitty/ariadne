<?php

$ARCurrent->nolangcheck=true;
if (($this->CheckLogin("edit") || $this->CheckLogin("add", ARANYTYPE)) && $this->CheckConfig()) {

	$this->call("system.save.tempfile.phtml");
	if ($this->error) {
		echo '<div class="error">' . $this->error . '</div>';
		echo '<script type="text/javascript">alert("' . AddCSlashes($this->error, ARESCAPE) . '"); </script>';
	}

	foreach ($AR->nls->list as $language => $language_name) {
		if (!$this->getdata("name", $language)) {
			if (($file=$this->getdata("file", $language)) && preg_match("|[^\/\\\]*\$|", $file, $matches)) {
				$arFilename = preg_replace("|[^a-z0-9\./_-]|i", "-", $matches[0]);
				$_POST[$language]["name"] = $arFilename;
			}
		}
	}

	$arResult = $wgWizFlow;
}

?>
