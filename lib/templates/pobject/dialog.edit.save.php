<?php
	$ARCurrent->nolangcheck=true;
	$arIsNewObject=$this->arIsNewObject;
	$this->call("system.save.data.phtml",$arCallArgs);
	if ($this->error) {
		?>
			<font color="red" face="arial,helvetica,sans-serif" size=+1><?php echo $this->error; ?></font><p>
		<?php
	} else {
		$this->unlock();
		?>
			<script type="text/javascript"> 
				if (top.window.opener) {
					if (top.window.opener.objectadded) {
						currentpath = window.opener.muze.ariadne.registry.get('path');
						if (currentpath == '<?php echo $this->parent; ?>') {
							window.opener.muze.ariadne.explore.objectadded();
						} else if (currentpath == '<?php echo $this->path; ?>') {
							window.opener.muze.ariadne.explore.tree.refresh('<?php echo $this->path; ?>');
							window.opener.muze.ariadne.explore.sidebar.view('<?php echo $this->path; ?>');
							window.opener.muze.ariadne.explore.browseheader.view('<?php echo $this->path; ?>');
						}
					}
					<?php
						if (!$arIsNewObject || $this->getdata("arCloseWindow", "none")) {
					?>
							top.window.close();
					<?php
						} else {
					?>
							top.window.location.href='dialog.add.php';
					<?php
						}
					?>
				} else if (top.window.dialogArguments) {
					arr=new Array();
					arr['type']='<?php echo $this->type; ?>';
					arr['name']='<?php echo AddCSlashes($this->nlsdata->name, ARESCAPE); ?>';
					arr['path']='<?php echo $this->path; ?>';
					top.window.returnValue=arr;
					top.window.close();
				}
			</script>
		<?php
	}
?>