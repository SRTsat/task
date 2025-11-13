<?php
namespace App\Http\Middleware;

use Closure;

class OutputResponse
{
    public $variable;
	public $function_name;	
	public $html_tag;
	public $char_per_line = 111;
	

	public function __construct()
	{
		 $this->variable  = 'html_data';
		 $this->function_name  = 'html_encoder';
		 $this->html_tag  = 'html_encoder_div';
		 
		 if($this->char_per_line % 3 != 0)
		 {
			 throw new Exception('Characters per line must be divisible by 3');
		 }
	}

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (env('APP_ENV') === 'production' && ! $request->ajax())
        {
            $content = $response->getContent();

            // Webshark
            $class = "\\WebSharks\\HtmlCompressor\\Core";
            if (class_exists($class))
            {
                $htmlOptions = [
                    'css_exclusions' => [],
                    'js_exclusions' => ['.php?*', '.html?*', '.htm?*'],
                    'uri_exclusions' => [],

                    'cache_expiration_time' => '1 Days',
                    'cache_dir_public' => public_path('assets/cache'),
                    'cache_dir_private' => storage_path('app/cache/assets'),
                    'cache_dir_url_public' => $request->getSchemeAndHttpHost() . '/assets/cache',

                    'current_url_scheme' => $request->getScheme(),
                    'current_url_host' => $request->getHost(),
                    'current_url_uri' => $request->getRequestUri(),

                    'compress_combine_head_body_css' => TRUE,
                    'compress_combine_head_js' => TRUE,
                    'compress_combine_footer_js' => TRUE,
                    'compress_inline_js_code' => TRUE,
                    'compress_inline_css_code' => TRUE,
                    'compress_css_code' => TRUE,
                    'compress_js_code' => TRUE,
                    'compress_html_code' => TRUE,

                    'benchmark' => FALSE,
                    'product_title' => 'HTML Compressor',
                    'vendor_css_prefixes' => ['moz','webkit','khtml','ms','o']
                ];
                $htmlCompressor = new $class($htmlOptions);
                // $content = $this->HtmlEncryptor($content);
                $content = $htmlCompressor->compress($content);

                $response->setContent($content);
            }
        }

        return $response;
    }

    /**
     * @param $response
     * @return bool
     */
    protected function isResponseObject($response): bool
    {
        return is_object($response) && $response instanceof Response;
    }

    /**
     * @param Response $response
     * @return bool
     */
    protected function isHtmlResponse(Response $response): bool
    {
        return strtolower(strtok($response->headers->get('Content-Type'), ';')) === 'text/html';
    }

    public function HtmlEncryptor($buffer)
	{
		if ( rand(0, 1) ) {
			
			$code = "<script type=\"text/javascript\" language=\"Javascript\">function ".$this->function_name."(s){var i=0,out='';l=s.length;for(;i<l;i+=3){out+=String.fromCharCode(parseInt(s.substr(i,2),16));}document.write(out);}</script>";
			$out = $this->function_name."(".$this->variable.");\n</script>";
			}else{
				$code = "<script type=\"text/javascript\" language=\"Javascript\">function ".$this->function_name."(s){var i=0,out='';l=s.length;for(;i<l;i+=3){out+=String.fromCharCode(parseInt(s.substr(i,2),16));}document.getElementById('".$this->html_tag."').innerHTML=out;}</script>";
				$out = "document.write('<div id=".$this->html_tag."></div>');\n".$this->function_name."(".$this->variable.");\n</script>";
			}
			
		$output  = "<script type=\"text/javascript\" language=\"Javascript\">\n";
		$output .= "document.write(unescape('".$this->JsEscape($code)."'));\n";
		$output .= "var ".$this->variable."='';\n";
		$output .= $this->Encryptor($buffer);
		$output .= $out;
		$noscript = '<noscript><div style="color:white;background:red;padding:20px;text-align:center"><tt><strong><big>For functionality of this site it is necessary to enable JavaScript. <br><br> Here are the <a target="_blank"href="http://www.enable-javascript.com/" style="color:white">instructions how to enable JavaScript in your web browser</a>.</big></strong></tt></div></noscript>';

		return ($output.$noscript);
		
	}
	
	public function Encryptor( $in ) {
		
		$out = '';
		$in = utf8_decode($in);
		$in = htmlentities($in);
		$in = $this->HTMLSpecialCharsDecode($in);
		for ( $i = 0; $i < strlen( $in ); $i++)
		{
			$hex = dechex( ord($in[$i]) );
			if ( $hex == '' )
			{
				$temp = urlencode( $in[$i] );
				$temp = str_replace('%', '', $temp);
				$out = $out.$temp.'.';
			} else {
				$out = $out.((strlen($hex)==1) ? ( '0'.strtoupper( $hex ) ):( strtoupper( $hex ) ) ).'.';
			}
		}
		$out = str_replace('+', '20.', $out);
		$out = str_replace('_', '5F.', $out);
		$out = str_replace('-', '2D.', $out);
		$out = $this->variable."+='".chunk_split($out,$this->char_per_line, "';\n".$this->variable."+='")."';\n";
		$out = str_replace("html_encoder_data+='';\n", '', $out);

		return $out;
		
	}
	
	public function HTMLSpecialCharsDecode($str, $quote_style = ENT_COMPAT)
	{
		return strtr($str, array_flip(get_html_translation_table(HTML_SPECIALCHARS, $quote_style)));
	}
	
	public function JsEscape($in)
	{
		$out = '';
		for ($i=0;$i<strlen($in);$i++)
		{
			$hex = dechex(ord($in[$i]));
			if ($hex=='')
				$out = $out.urlencode($in[$i]);
		    else
			$out = $out .'%'.((strlen($hex)==1) ? ('0'.strtoupper($hex)):(strtoupper($hex)));
		}
		
		return $out;
   
   }
}