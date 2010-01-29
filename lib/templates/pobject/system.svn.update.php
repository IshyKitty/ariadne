<?php
	if ($this->CheckLogin("layout") && $this->CheckConfig()) {
		$this->resetloopcheck();

		$fstore	= $this->store->get_filestore_svn("templates");
		$svn	= $fstore->connect($this->id, $this->getdata("username"), $this->getdata("password"));

		$type		= $this->getvar("type");
		$function 	= $this->getvar("function");
		$language 	= $this->getvar("language");

		if ($type && $function && $language) {
			$filename = $type . "." . $function . "." . $language . ".pinp";
		}

		$repository = $svn['info']['URL'];

		if (isset($repository)) {
			$repository = rtrim($repository, "/") . "/" . $repo_subpath;
			
			$updating = $this->path;
			if( $filename ) {
				$updating .= $function . " {".$type.") [".$language."]";
			} 
			
			echo "<span class='svn_headerline'>Updating ".$updating." from ".$repository."</span>\n\n";
			flush();
			$result = $fstore->svn_update($svn, $filename, $this->getdata("revision"));
			if ($result) {
				$templates = array();
				foreach ($result as $item) {
					$templates[] = $item['name'];
					$props = $fstore->svn_get_ariadne_props($svn, $item['name']);
					if( $item["status"]  == "A" ) {
						echo "<span class='svn_addtemplateline'>Adding ".$this->path.$props["ar:function"]." (".$props["ar:type"].") [".$props["ar:language"]."] ".( $props["ar:default"] == '1' ? $ARnls["default"] : "")."</span>\n";
					} elseif( $item["status"] == "U" || substr(ltrim($item['name']),0,2) == 'U ' ) { // substr to work around bugs in SVN.php
						echo "<span class='svn_revisionline'>Updating ".$this->path.$props["ar:function"]." (".$props["ar:type"].") [".$props["ar:language"]."] ".( $props["ar:default"] == '1' ? $ARnls["default"] : "")."</span>\n";
					} elseif( $item["status"] == "M" || $item["status"] == "G" ) {
						echo "<span class='svn_revisionline'>Merging ".$this->path.$props["ar:function"]." (".$props["ar:type"].") [".$props["ar:language"]."] ".( $props["ar:default"] == '1' ? $ARnls["default"] : "")."</span>\n";
					} elseif( $item["status"] == "C" ) {
						echo "<span class='svn_revisionline'>Conflict ".$this->path.$props["ar:function"]." (".$props["ar:type"].") [".$props["ar:language"]."] ".( $props["ar:default"] == '1' ? $ARnls["default"] : "")."</span>\n";
					} else {
						echo "FIXME: ";
						print_r($item);
						echo "\n";
					}
					flush();
				}
	
				$this->call(
					"system.svn.compile.templates.php", 
					array(
						'templates' 	=> $templates,
						'fstore'	=> $fstore,
						'svn'		=> $svn
					)
				);
			}
			if ($result === false) {
				echo "Update failed.\n";
				if (count($errs = $fstore->svnstack->getErrors())) {
					foreach ($errs as $err) {
						echo $err['message']."\n";
					}
				}
			}
		}
	}
?>