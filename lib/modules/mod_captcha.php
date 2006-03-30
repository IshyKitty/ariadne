<?php
	DEFINE(CAPTCHA_TTF_FOLDER, '/var/lib/defoma/x-ttcidfont-conf.d/dirs/TrueType/');

	require_once($this->store->get_config('code')."modules/mod_captcha/hn_captcha.class.x1.php");

	class mod_captcha extends hn_captcha_X1 {

		function mod_captcha($config) {
			$this->config = $config;
			hn_captcha_X1::hn_captcha_X1($config);
		}
		
		function get_filename_url($public="") {
			if ($public == "") {
				$public = $this->public_key;
			}
			return $this->config['url'].$this->config['template']."?show=$public";
		}

	}

	class captcha {

		function process($aconfig='') {
			// ConfigArray
			$context = $this->getContext();
			$template = $this->getvar('arCallFunction');

			if (!is_array($aconfig)) {
				$aconfig = Array();
			}

			$temp = $this->store->get_config('files').'temp/';
			$config = Array(
					'template'		 => $template,
					'url'			 => $this->make_url(),
		            'tempfolder'     => $temp,      
					'TTF_folder'     => CAPTCHA_TTF_FOLDER, 
		                                // mixed (array or string): basename(s) of TrueType-Fontfiles
					'TTF_RANGE'      => Array(
											'Arial_Bold.ttf',
											'Courier_New_Bold.ttf',
											'Comic_Sans_MS_Bold.ttf',
											'Georgia_Bold.ttf',
											'Times_New_Roman_Bold.ttf',
											'Trebuchet_MS_Bold.ttf'
					),

		            'chars'          => 5,       // integer: number of chars to use for ID
		            'minsize'        => 20,      // integer: minimal size of chars
		            'maxsize'        => 30,      // integer: maximal size of chars
		            'maxrotation'    => 25,      // integer: define the maximal angle for char-rotation, good results are between 0 and 30

		            'noise'          => FALSE,    // boolean: TRUE = noisy chars | FALSE = grid
		            'websafecolors'  => FALSE,   // boolean
		            'refreshlink'    => TRUE,    // boolean
		            'lang'           => 'en',    // string:  ['en'|'de']
		            'maxtry'         => 3,       // integer: [1-9]

		            'badguys_url'    => '/',     // string: URL
		            'secretstring'   => 'A very, very secret string which is used to generate a md5-key!',
		            'secretposition' => 15,      // integer: [1-32]

		            'debug'          => FALSE
			);

			foreach ($aconfig as $akey => $aval) {
				switch ($akey) {
					case 'maxsize':
					case 'maxrotation':
					case 'noise':
					case 'websafecolors':
					case 'lang':
					case 'maxtry':
					case 'badguys_url':
					case 'secretstring':
					case 'secretposition':
					case 'minsize':
					case 'chars':
					case 'debug':
						$config[$akey] = $aval;
					break;
				}
			}

			$captcha		= new mod_captcha($config);
			if ($this->getvar('show')) {
				$captchaCase = 'show';
			} else {
				$case			= $captcha->validate_submit();
				switch ($case) {
					case 1:
						$captchaCase = 'valid';
					break;
					case 2:
						$captchaCase = 'invalid';
					break;
					case 3:
						$captchaCase = 'expired';
					break;
					default:
						$captchaCase = 'normal';
					break;
				}
			}

			$context['captcha'] = $captcha;
			$this->setContext($context);
			return $captchaCase;
		}

		function showImg($captchaImg) {
			$captchaImg = $this->getvar('show');
			$captchaImg = ereg_replace("[\\\/]", "", $captchaImg);
			$filename = $this->store->get_config('files').'temp/hn_captcha_'.$captchaImg.'.jpg';
//			ldSetContent('image/jpg');
			readfile($filename);
		}

		function getImgSrc() {
			$context = $this->getContext();
			$captcha = $context['captcha'];
			$captcha->make_captcha();
			return $captcha->get_filename_url();
		}

		function getFormField() {
			$context = $this->getContext();
			$captcha = $context['captcha'];
			$captcha->make_captcha();
			$elements  = $captcha->public_key_input();
			$try = $captcha->get_try(FALSE);
			$elements .= "<input type=\"hidden\" name=\"hncaptcha\" value=\"$try\">";
			$elements .= "<input type=\"text\" class=\"captcha\" name=\"private_key\" value=\"\" maxlength=\"".$captcha->chars."\" size=\"".$captcha->chars."\">";
			return $elements;
		}
	}

	class pinp_captcha {
		function _process($config='') {
			return captcha::process($template, $config);
		}

		function _showImg() {
			$captchaImg = $this->getvar('show');
			return captcha::showImg($captchaImg);
		}

		function _getImgSrc() {
			return captcha::getImgSrc();
		}

		function _getFormField() {
			return captcha::getFormField();
		}
	}

?>