<?php
	if ($this->CheckLogin("layout") && $this->CheckConfig()) {
		echo "<pre class='svn_result'>";
		set_time_limit(0);
		$this->find($this->path, '', "system.svn.unsvn.php", $arCallArgs);
		echo "</pre>";
		$this->call("window.opener.objectadded.js");
	}
?>