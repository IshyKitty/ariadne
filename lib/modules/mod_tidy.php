<?php
    /******************************************************************
     mod_tidy.php                                          Muze Ariadne
     ------------------------------------------------------------------
     Author: Muze (info@muze.nl)
     Date: 26 november 2002

     Copyright 2002 Muze

     This file is part of Ariadne.

     Ariadne is free software; you can redistribute it and/or modify
     it under the terms of the GNU General Public License as published 
     by the Free Software Foundation; either version 2 of the License, 
     or (at your option) any later version.
 
     Ariadne is distributed in the hope that it will be useful,
     but WITHOUT ANY WARRANTY; without even the implied warranty of
     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     GNU General Public License for more details.

     You should have received a copy of the GNU General Public License
     along with Ariadne; if not, write to the Free Software 
     Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  
     02111-1307  USA

    -------------------------------------------------------------------

     Description:

	   This module calls the html tidy executable with the given
	   options and returns 'clean' html.

    ******************************************************************/

	class tidy {

		function tidy($config) {
			$this->tidy=$config["path"];
			$this->temp=$config["temp"];
			$this->options=$config["options"];
		}

		function clean($html) {
			$file = tempnam($this->temp,'tidy-php-tmp');
			$errfile = tempnam($this->temp,'tidy-php-err');

			$fd = fopen($file,"w");
			fwrite($fd,$html,strlen($html));
			fclose($fd);

			$pd = popen($this->tidy." --error-file ".$errfile." ".$this->options." ".$file,"r");
			while (!feof($pd)) {
				$outhtml .= fread($pd, 1024);
			}
			pclose($pd);

			$fd = fopen($errfile,"r");
			while (!feof($fd)) {
				$errors .= fread($fd, 1024);
			}
			fclose($fd);

			unlink($file);
			unlink($errfile);
			$ret['html'] = $outhtml;
			$ret['errors'] = $errors;
			return $ret;
		}
	}
?>