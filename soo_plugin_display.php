<?php

$plugin['name'] = 'soo_plugin_display';
$plugin['version'] = '0.1.4';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/txp/';
$plugin['description'] = 'Display info about installed plugins';
$plugin['type'] = 1; // load on admin side for prefs management

if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001);
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); 
$plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

if (!defined('txpinterface'))
	@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

  //---------------------------------------------------------------------//
 //						soo_plugin_pref compatibility					//
//---------------------------------------------------------------------//


global $soo_plugin_display_prefs;

@require_plugin('soo_plugin_pref');				// optional
add_privs('plugin_prefs.soo_plugin_display','1,2');
add_privs('plugin_lifecycle.soo_plugin_display','1,2');
register_callback('soo_plugin_display_prefs', 'plugin_prefs.soo_plugin_display');
register_callback('soo_plugin_display_prefs', 'plugin_lifecycle.soo_plugin_display');

	// load prefs if soo_plugin_pref enabled, else load defaults
$soo_plugin_display_prefs = function_exists('soo_plugin_pref_vals') ? 
	soo_plugin_pref_vals('soo_plugin_display') : array();
foreach ( soo_plugin_display_defaults() as $name => $arr )
		// just in case prefs/defaults do not match;
		// might happen if plugin upgraded while soo_plugin_pref disabled
	if ( ! array_key_exists($name, $soo_plugin_display_prefs) )
		$soo_plugin_display_prefs[$name] = $arr['val'];

	// callback for plugin_prefs/plugin_lifecycle events
function soo_plugin_display_prefs( $event, $step ) {
	if ( function_exists('soo_plugin_pref') )
		return soo_plugin_pref($event, $step, soo_plugin_display_defaults());
	
		// message to install soo_plugin_pref
	if ( substr($event, 0, 12) == 'plugin_prefs' ) {
		$plugin = substr($event, 13);
		$message = '<p><br /><strong>' . gTxt('edit') . " $plugin " . 
			gTxt('edit_preferences') . ':</strong><br />' . gTxt('install_plugin') . 
			' <a href="http://ipsedixit.net/txp/92/soo_plugin_pref">soo_plugin_pref</a></p>';
		pagetop(gTxt('edit_preferences') . " &#8250; $plugin", $message);
	}
}

function soo_plugin_display_defaults( ) {
	return array(
		'default_form'		=>	array(
			'val'	=>	'',
			'html'	=>	'text_input',
			'text'	=>	'Default output form for <b>soo_plugin_display</b> tag',
		),
		'strip_style'		=>	array(
			'val'	=>	0,
			'html'	=>	'yesnoradio',
			'text'	=>	'Remove leading &lt;style&gt; element from Help text?',
		),
		'strip_title'		=>	array(
			'val'	=>	0,
			'html'	=>	'yesnoradio',
			'text'	=>	'Remove first &lt;h1&gt; element from Help text?',
		),
		'size_format'		=>	array(
			'val'	=>	'{size}&nbsp;KB',
			'html'	=>	'text_input',
			'text'	=>	'Default format string for <b>soo_plugin_size</b>',
		),
		'show_line_numbers'	=>	array(
			'val'	=>	':',
			'html'	=>	'text_input',
			'text'	=>	'Text to append to line numbers (leave blank to hide numbers)',
		),
		'tab_stop'			=>	array(
			'val'	=>	4,
			'html'	=>	'text_input',
			'text'	=>	'Spaces per tab in plugin code',
		),
	);
}

  //---------------------------------------------------------------------//
 //									Tags								//
//---------------------------------------------------------------------//

	// tag to select plugin(s) for display
	// can be used as single w/form or container
	// for plugin lists code and help are not retrieved
function soo_plugin_display( $atts, $thing = '' ) {
	global $soo_plugin_display, $soo_plugin_display_prefs;
	extract(lAtts(array(
		'name'			=>	'',
		'prefix'		=>	'',
		'form'			=>	$soo_plugin_display_prefs['default_form'],
		'show_inactive'	=>	0,
		'sort'			=>	'name asc',
		'wraptag'		=>	'',
		'break'			=>	'',
		'class'			=>	'',
		'html_id'		=>	'',
	), $atts));
	
	$columns = 'author, author_uri, version, description, name, ' .
		'round(char_length(code)/1024, 1) as size' . ( $name ? ', help, code' : '' );
		
	if ( $name or $prefix )
		$where = ( $name ? "name = '$name'" : "name like '$prefix%'" )
			. ( ( $show_inactive or $name ) ? '' : ' and status = 1' );
	else
		$where = $show_inactive ? 1 : 'status = 1';
	$where .= " order by $sort";
	
	if ( ! $data = safe_rows($columns, 'txp_plugin', $where) ) 
		return;
		
	foreach ( $data as $r ) {
		$soo_plugin_display = $r;
		$out[] = $thing ? parse($thing) : parse_form($form);
	}
	
	if ( isset($out) ) 
		return doWrap($out, $wraptag, $break, $class, '', '', '', $html_id);
}

	// basic output tags: display field direct from database
function soo_plugin_author_uri( ) { return _soo_plugin_field('author_uri'); }
function soo_plugin_version( ) { return _soo_plugin_field('version'); }
function soo_plugin_description( ) { return _soo_plugin_field('description'); }

	// as above, but with option to make output a link to author's website
function soo_plugin_author( $atts ) { return _soo_plugin_field('author_uri', $atts); }
function soo_plugin_name( $atts ) { return _soo_plugin_field('name', $atts); }

	// display plugin help, with options to remove leading style and/or h1 elements,
	// and option to restrict display to named section
function soo_plugin_help( $atts ) { 
	global $soo_plugin_display_prefs;
	extract(lAtts(array(
		'strip_style'	=>	$soo_plugin_display_prefs['strip_style'],
		'strip_title'	=>	$soo_plugin_display_prefs['strip_title'],
		'section_id'	=>	'',		// HTML id for header element to display
		'h_plus'		=>	0,		// transpose header levels by this amount
	), $atts));
	$help = _soo_plugin_field('help');
	
		// remove leading <style> element
	if ( $strip_style and preg_match('/^<style/', $help) )
		$help = preg_replace('/^[\s\S]+?<\/style>([\s\S]+)$/', '$1', $help);
		
		// remove leading <h1> element
	if ( $strip_title )
		$help = preg_replace('/^([\s\S]+?)<h1[\s\S]+?<\/h1>([\s\S]+)$/', '$1$2', $help);
	
		// display from named header element to next header with same or lower h-level
	if ( $section_id ) {
		$pattern = "/^([\s\S]+)<h(\d)([^>]+id=['\"]$section_id('|\").+?>[\s\S]+)$/";
		if ( preg_match($pattern, $help, $match) ) {
			list( , , $h_num, $remainder) = $match;
			$help = '';
			$pattern = '/^([\s\S]+?)<h(\d)([\s\S]+)$/';
			$remainder = "<h$h_num$remainder";
			while ( preg_match($pattern, $remainder, $match) ) {
				list( , $keep, $next_h, $remainder) = $match;
				$help .= $keep;
				$remainder = "<h$next_h$remainder";
				if ( $h_num >= $next_h )
					$remainder = '';
			}
			if ( ! $help )
				$help = $remainder;
		}
	}
		
		// transpose HTML header levels.
	if ( $h_plus ) {
		for ( $i = 1; $i <= 6; $i++ ) {
			$j = $i + $h_plus;
			if ( $j < 1 ) $j = 1;
			if ( $j > 6 ) $j = 6;
			$old_tag[] = "<h$i";
			$old_tag[] = "</h$i";
			$new_tag[] = "<h$j";
			$new_tag[] = "</h$j";
		}
		$help = str_replace($old_tag, $new_tag, $help);		
	}
	
	return $help;
}

	// display installed code size in KB
function soo_plugin_size( $atts ) {
	global $soo_plugin_display_prefs;
	extract(lAtts(array(
		'format'	=>	$soo_plugin_display_prefs['size_format'],
	), $atts));
	$size = _soo_plugin_field('size');
	return str_replace('{size}', $size, $format);
}

	// display plugin source code
	// code highlighting with named classes for styling
	// various options for line numbering
	// display full source or named function, class, or method
function soo_plugin_code( $atts ) {
	global $soo_plugin_display_prefs;
	extract(lAtts(array(
		'show_line_numbers'		=>	$soo_plugin_display_prefs['show_line_numbers'],
		'reindex_lines'			=>	0,
		'tab_stop'				=>	$soo_plugin_display_prefs['tab_stop'],
		'function'				=>	'',
		'php_class'				=>	'',
		'class'					=>	'soo_plugin_code',
		'html_id'				=>	'',
	), $atts));
	
		// names of code highlight types from ini_get()
		// highlight_string() output style attributes will be replaced by named classes
	$keys = array('bg', 'comment', 'default', 'html', 'keyword', 'string');
	foreach ( $keys as $k ) {
		$find[] = 'style="color: ' . ini_get("highlight.$k") . '"';
		$replace[] = "class=\"php_$k\"";
	}
	$find[] = '<code>';
	$find[] = '</code>';
	$raw_code = trim(_soo_plugin_field('code'));
	
	$start_line = 1;
	$match_index = 2;
	$safety_check = true;
	
		// find named method: assumes end brace one tab in from start of line
	if ( $php_class and $function ) {
		$pattern = '/([\s\S]*(abstract|)\s*class\s+' 
			. $php_class . '\s+.*?\{[\s\S]+?)(\t(public|private|protected)?\s*function\s+' 
			. $function . '\s*\([\s\S]+?\n\t}.*)[\s\S]*/';
		$match_index ++;
	}
	
		// find named function: assumes end brace at start of line
	elseif ( $function )
		$pattern = '/([\s\S]*)((public|private|protected)?\s*function\s+' 
			. $function . '\s*\([\s\S]+?\n}.*)/';
	
		// find named class: assumes end brace at start of line
	elseif ( $php_class )
		$pattern = '/([\s\S]*)((abstract|)\s*class\s+' 
			. $php_class . '\s+.*?\{[\s\S]+?\n}.*)[\s\S]*/';
	
	else
		$safety_check = false;
	
		// safety check is because the preg_match() can take a long time if there is no match
	if ( $safety_check ) {
		if ( $function and ! preg_match("/$function\s*\(/", $raw_code) )
			return;
		if ( $php_class and ! preg_match("/class\s*$php_class\s+/", $raw_code) )
			return;
	}
	
	if ( isset($pattern) ) {
		if ( preg_match($pattern, $raw_code, $match) ) {
			$raw_code = $match[$match_index];
			
				// if not reindexing, find starting line number in full source code
			$start_line = $reindex_lines ? 
				$reindex_lines : count(explode("\n", $match[1]));
		}
		else 
			return;
	}
	
		// convert tabs to spaces
	$lines = _soo_detab(explode("\n", $raw_code), $tab_stop);
	
	$total_lines = $start_line + count($lines) - 1;
	foreach ( $lines as $i => $line ) {
		if ( preg_match('/\S/', $line) )
			$line = preg_replace(
				'/&lt;\?php&nbsp;([\s\S]*)&nbsp;<\/span><span[^>]*>\?&gt;/', '$1',
				str_replace($find, $replace, 
					highlight_string("<?php " . $line . " ?>", true)));
		if ( $show_line_numbers ) {
			$i += $start_line;
			$line = "$i $show_line_numbers $line";
			if ( $i < 1000 and $total_lines > 999 ) $line = sp . $line;
			if ( $i < 100 and $total_lines > 99 ) $line = sp . $line;
			if ( $i < 10 ) $line = sp . $line;
		}
		$code[] = $line;
	}
	
	$tag_atts = ( $class ? " class=\"$class\"" : '' ) . ( $html_id ? " id=\"$html_id\"" : '' );
	
	return "<code$tag_atts>" . implode("<br />\n", $code) . '</code>';
}

  //---------------------------------------------------------------------//
 //							Support Functions							//
//---------------------------------------------------------------------//

function _soo_plugin_field( $field, $atts = null ) {
	if ( is_array($atts) )
		extract(lAtts(array(
			'link'	=>	1,
		), $atts));
	global $soo_plugin_display;
	$author_uri = $soo_plugin_display['author_uri'];
	if ( isset($soo_plugin_display[$field]) ) {
		$out = $soo_plugin_display[$field];
		return ( ! empty($link) and $author_uri ) ? href($out, $author_uri) : $out;
	}
}

	// convert tabs to spaces, aligned to tab stops
function _soo_detab( $lines, $tab_stop ) {
	$out = array();
	foreach ( $lines as $line ) {
		$bucket = array();
		foreach ( str_split($line) as $char )
			if ( $char == t ) {
				$add = $tab_stop - fmod(count($bucket), $tab_stop);
				while ( $add ) {
					$bucket[] = ' ';
					$add --;
				}
			}
			else
				$bucket[] = $char;
		$out[] = implode('', $bucket);
	}
	return $out;
}

# --- END PLUGIN CODE ---

if (0) {
?>
<!-- CSS SECTION
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#sed_help pre {padding: 0.5em 1em; background: #eee; border: 1px dashed #ccc;}
div#sed_help h1, div#sed_help h2, div#sed_help h3, div#sed_help h3 code {font-family: sans-serif; font-weight: bold;}
div#sed_help h1, div#sed_help h2, div#sed_help h3 {margin-left: -1em;}
div#sed_help h2, div#sed_help h3 {margin-top: 2em;}
div#sed_help h1 {font-size: 2.4em;}
div#sed_help h2 {font-size: 1.8em;}
div#sed_help h3 {font-size: 1.4em;}
div#sed_help h4 {font-size: 1.2em;}
div#sed_help h5 {font-size: 1em;margin-left:1em;font-style:oblique;}
div#sed_help h6 {font-size: 1em;margin-left:2em;font-style:oblique;}
div#sed_help li {list-style-type: disc;}
div#sed_help li li {list-style-type: circle;}
div#sed_help li li li {list-style-type: square;}
div#sed_help li a code {font-weight: normal;}
div#sed_help li code:first-child {background: #ddd;padding:0 .3em;margin-left:-.3em;}
div#sed_help li li code:first-child {background:none;padding:0;margin-left:0;}
div#sed_help dfn {font-weight:bold;font-style:oblique;}
div#sed_help .required, div#sed_help .warning {color:red;}
div#sed_help .default {color:green;}
</style>
# --- END PLUGIN CSS ---
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---
 <div id="sed_help">

h1. soo_plugin_display

 <div id="toc">

h2. Contents

* "Overview":#overview
* "Usage":#usage
* "Tags":#tags
** "soo_plugin_display":#soo_plugin_display
** "soo_plugin_author_uri, soo_plugin_version, soo_plugin_description":#soo_plugin_author_uri
** "soo_plugin_author, soo_plugin_name":#soo_plugin_author
** "soo_plugin_size":#soo_plugin_size
** "soo_plugin_help":#soo_plugin_help
** "soo_plugin_code":#soo_plugin_code
* "Examples":#examples
* "Preferences":#preferences
* "History":#history

 </div>

h2(#overview). Overview

Display information about installed plugins.

h2(#usage). Usage

The @soo_plugin_display@ tag works as a container or with a form that retrieves most of the txp_plugin fields for the plugin specified in the @name@ attribute. Place any of the other tags (@soo_plugin_author@, etc.) inside the container or form to display your choice of fields.

If @name@ is left blank (the %(default)default%), @soo_plugin_display@ will retrieve all plugins (optionally restricted by plugin prefix or status). In this case the plugin code and help will *not* be retrieved, so @soo_plugin_code@ and @soo_plugin_help@ will not produce output.

The plugin is compatible with "soo_plugin_pref":http://ipsedixit.net/txp/92/soo_plugin_pref, allowing you to adjust some of the default tag attribute values.

h2(#tags). Tags

All tags (except for @soo_plugin_display@) %(required)must% be in a @soo_plugin_display@ container or form to produce output. 

h3(#soo_plugin_display). soo_plugin_display

Retrieve a named plugin or a list of plugins; iterate over each plugin with the tag contents or specified form.

pre.. <txp:soo_plugin_display form="foo" />    <!-- use with a form -->

<txp:soo_plugin_display>                 <!-- or as a container -->
	...
</txp:soo_plugin_display>

h4. Attributes

* @name@ _(plugin name)_ Plugin to display. If blank (the %(default)default%), retrieve a list of plugins (<dfn>list mode</dfn>)
* @form@ _(Txp form name)_ Form to display output. If empty, container tag required for output. %(default)Default% is empty (can be changed in prefs).

The remaining attributes are effective only in list mode (i.e., when @name@ is left blank).

* @prefix@ _(plugin author prefix)_ select only plugins with this prefix
* @show_inactive@ _(boolean)_ %(default)default% "0", whether to include inactive plugins
* @sort@ _(MySQL sort value)_ %(default)default% "name asc" (in addition to column names, "size" is also available for sorting)
* @wraptag@ _(HTML tag name, without brackets)_
* @break@ _(HTML tag name, without brackets)_
* @class@ _(HTML class attribute)_ applied to @wraptag@
* @html_id@ _(HTML ID attribute)_ applied to @wraptag@

h3(#soo_plugin_author_uri). soo_plugin_author_uri, soo_plugin_version, soo_plugin_description

No attributes: each simply displays the corresponding field straight from @txp_plugin@.

pre. <txp:soo_plugin_author_uri />
<txp:soo_plugin_version />
<txp:soo_plugin_description />

h3(#soo_plugin_author). soo_plugin_author, soo_plugin_name

As above, but these accept an optional @link@ attribute.

pre. <txp:soo_plugin_author />
<txp:soo_plugin_name />

h4. Attributes

* @link@ _(boolean)_ whether to make output a link to the plugin author's website, %(default)default% "1"

h3(#soo_plugin_size). soo_plugin_size

Display the plugin's installed code size in KB (counting 1 character = 1 byte)

pre. <txp:soo_plugin_size />

h4. Attributes

* @format@ _(text)_ %(default)default% '{size}&amp;nbsp;KB', can be changed in prefs.

The plugin outputs the value of @format@, first replacing any occurrences of '{size}' with the size in KB.

h3(#soo_plugin_help). soo_plugin_help

Display the plugin's help text.

pre. <txp:soo_plugin_help />

%(warning)Does not work in list mode% (i.e., when @soo_plugin_display@ has a blank @name@ attribute).

h4. Attributes

* @strip_style@ _(boolean)_ whether or not to remove any leading @<style>@ element (%(default)default% "1", can be changed in prefs)
* @strip_title@ _(boolean)_ whether or not to remove any leading @<h1>@ element (%(default)default% "1", can be changed in prefs)
* @section_id@ _(HTML id value)_ start display from identified header element, continue till next header of same or lower level
* @h_plus@ _(integer)_ transpose HTML header levels by this amount

@strip_style@ looks for an opening @<style>@ tag at the very start of the Help section. @strip_title@ looks for the first occurence of @<h1>@. Both do a non-greedy match looking for the closing tag.

With @section_id@, display begins from the HTML header element with the specified id. Display continues till the next HTML header with the same or lower level (@<h2>@ considered lower than @<h3>@, for example) or until the end if no such header is found.

@h_plus@ can be helpful when breaking a long help text into several web pages, in conjuction with @section_id@. For example, @h_plus="-2"@ will transpose all @h6@ elements to @h4@, all @h5@ elements to @h3@, etc.

h3(#soo_plugin_code). soo_plugin_code

Display plugin source code, by %(default)default% the whole thing, or selected code determined by the @function@ and/or @php_class@ attributes.

pre. <txp:soo_plugin_code />

%(warning)Does not work in list mode% (i.e., when @soo_plugin_display@ has a blank @name@ attribute).

h4. Attributes

* @show_line_numbers@ _(text)_ If set, text to append to each line number. If blank, do not show line numbers. %(default)default% ":" (can be changed in prefs).
* @reindex_lines@ _(integer)_ With @function@ and/or @php_class@, renumber lines starting from the value given. %(default)Default% "0", do not reindex.
* @tab_stop@ _(integer)_ length of tab stop
* @function@ _(text)_ Show only this function. Use in combination with @php_class@ to show only this method.
* @php_class@ _(text)_ Show only this PHP class
* @class@ _(html class name)_ for &lt;code&gt; element
* @html_id@ _(html id name)_ for &lt;code&gt; element

The function/class search isn't thorough, and is based on my coding style. In the case of a function (outside a class) or class, it simply stops at the first non-indented closing brace ("}") that occurs after the function or class name. Same for a method (function inside a class) but with the closing brace indented one tab.

The code highlighting is based on the PHP @highlight_string()@ function. The @style@ declarations produced by @highlight_string()@ are replaced by @class@ declarations. The important ones:

* @php_comment@ comments
* @php_keyword@ keywords, operators, brackets, semicolons, etc.
* @php_default@ function names (including core PHP functions)
* @php_string@ strings

Everything but the line numbers will be in a @span@ with one of those class names. The whole thing is wrapped in a @code@ element.

Tabs are converted to spaces, to stay aligned with tab stops as set in plugin preferences or the @tag_stop@ attribute.

h2(#examples). Examples

h3. List all active plugins as a table

showing plugin author (as a link to the plugin author's website) and description

pre. <txp:soo_plugin_display wraptag="table" break="tr">
<td><txp:soo_plugin_name /></td><td><txp:soo_plugin_description /></td>
</txp:soo_plugin_display>

h3. Display plugin help

pre. <txp:soo_plugin_display name="soo_image">
<txp:soo_plugin_help />
</txp:soo_plugin_display>

h3. Display full source code

leaving off the line numbers

pre. <txp:soo_plugin_display name="soo_plugin_pref">
<txp:soo_plugin_code show_line_numbers="" />
</txp:soo_plugin_display>

h3. Display a single method

renumbering the lines, starting from 1

pre. <txp:soo_plugin_display name="soo_txp_obj">
<txp:soo_plugin_code php_class="soo_html" function="tag" reindex_lines="1" />
</txp:soo_plugin_display>

h2(#preferences). Preferences

If you have the "soo_plugin_pref":http://ipsedixit.net/txp/92/soo_plugin_pref preference management system installed, you can adjust some of the default tag attribute values. Preference settings:

* Default value for @soo_plugin_display@'s @form@ attribute
* Default value for @soo_plugin_help@'s @strip_style@ attribute
* Default value for @soo_plugin_help@'s @strip_title@ attribute
* Default value for @soo_plugin_size@'s @format@ attribute
* Default value for @soo_plugin_code@'s @show_line_numbers@ attribute
* Default value for @soo_plugin_code@'s @tab_stop@ attribute

h2(#history). Version History

h3. 0.1.4 (7/4/2010, USA Independence Day)

@soo_plugin_help@ output can have HTML header levels transposed, using the @h_plus@ attribute

h3. 0.1.3 (9/27/2009)

For @soo_plugin_code@, tab to space conversion now maintains tab-stop alignment

h3. 0.1.2 (9/26/2009)

New attribute for @soo_plugin_help@:
* @section_id@, start output from header element with specified HTML id, continuing until next header element with same or lower level

h3. 0.1.1 (9/22/2009)

* Fixed: SQL bug in list mode

h3. 0.1 (9/18/2009)

Display most fields straight from the @txp_plugin@ table. Also,
* plugin name or author name can be automatically linked to plugin author's website
* @soo_plugin_help@ has options for stripping title and style first
* @soo_plugin_size@ shows installed code size
* @soo_plugin_code@ can display complete code or by function/class
* compatible with *soo_plugin_pref* preference management system

 </div>
# --- END PLUGIN HELP ---
-->
<?php
}

?>