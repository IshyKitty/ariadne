<?php
	if (!$this->validateFormSecret()) {
		error($ARnls['ariadne:err:invalidsession']);
		exit;
	}
	ldDisablePostProcessing();
	$ARCurrent->nolangcheck=true;
	if ($this->CheckLogin("layout") && $this->CheckConfig()) {
		echo "<pre class='svnresult'>\n";
		$this->call("system.svn.import.recursive.php", $arCallArgs);
		echo "</pre>\n";
		$this->call("window.opener.objectadded.js");
	}
?>
