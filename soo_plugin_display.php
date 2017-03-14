<?php
$plugin['version'] = '0.2.4';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/txp/';
$plugin['description'] = 'Display info about installed plugins';
$plugin['type'] = 1; // load on admin side for prefs management
$plugin['allow_html_help'] = 1;

defined('PLUGIN_HAS_PREFS') or define('PLUGIN_HAS_PREFS', 0x0001); 
defined('PLUGIN_LIFECYCLE_NOTIFY') or define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); 
$plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

if (! defined('txpinterface')) {
    global $compiler_cfg;
    @include_once('config.php');
    @include_once($compiler_cfg['path']);
}

# --- BEGIN PLUGIN CODE ---

if(class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('soo_plugin_display')
        ->register('soo_plugin_author_uri')
        ->register('soo_plugin_version')
        ->register('soo_plugin_description')
        ->register('soo_plugin_author')
        ->register('soo_plugin_name')
        ->register('soo_plugin_help')
        ->register('soo_plugin_size')
        ->register('soo_plugin_code')
        ;
}

@require_plugin('soo_plugin_pref');             // optional

if (@txpinterface == 'admin') {
    add_privs('plugin_prefs.soo_plugin_display','1,2');
    add_privs('plugin_lifecycle.soo_plugin_display','1,2');
    register_callback('soo_plugin_display_manage_prefs', 'plugin_prefs.soo_plugin_display');
    register_callback('soo_plugin_display_manage_prefs', 'plugin_lifecycle.soo_plugin_display');
}

function soo_plugin_display_manage_prefs($event, $step)
{
    if (function_exists('soo_plugin_pref')) {
        return soo_plugin_pref($event, $step, soo_plugin_display_pref_spec());
    }
        // message to install soo_plugin_pref
    if ( substr($event, 0, 12) == 'plugin_prefs' ) {
        $plugin = substr($event, 13);
        $message = '<p><br /><strong>'.gTxt('edit')." $plugin ".
            gTxt('edit_preferences').':</strong><br />'.gTxt('install_plugin').
            ' <a href="http://ipsedixit.net/txp/92/soo_plugin_pref">soo_plugin_pref</a></p>';
        pagetop(gTxt('edit_preferences')." &#8250; $plugin", $message);
    }
}

function soo_plugin_display_pref_spec()
{
    return array(
        'default_form'      =>  array(
            'val'   =>  '',
            'html'  =>  'text_input',
            'text'  =>  'Default output form for <b>soo_plugin_display</b> tag',
        ),
        'strip_style'       =>  array(
            'val'   =>  0,
            'html'  =>  'yesnoradio',
            'text'  =>  'Remove leading &lt;style&gt; element from Help text?',
        ),
        'strip_title'       =>  array(
            'val'   =>  0,
            'html'  =>  'yesnoradio',
            'text'  =>  'Remove first &lt;h1&gt; element from Help text?',
        ),
        'size_format'       =>  array(
            'val'   =>  '{size}&nbsp;KB',
            'html'  =>  'text_input',
            'text'  =>  'Default format string for <b>soo_plugin_size</b>',
        ),
        'highlight'         =>  array(
            'val'   =>  1,
            'html'  =>  'yesnoradio',
            'text'  =>  'Add syntax highlighting to <b>soo_plugin_code</b>?',
        ),
        'show_line_numbers' =>  array(
            'val'   =>  ':',
            'html'  =>  'text_input',
            'text'  =>  'Text to append to line numbers (leave blank to hide numbers)',
        ),
        'tab_stop'          =>  array(
            'val'   =>  4,
            'html'  =>  'text_input',
            'text'  =>  'Spaces per tab in plugin code',
        ),
    );
}

function soo_plugin_display_prefs($pref = null)
{
    static $prefs;
    if (! $prefs) {
        foreach (soo_plugin_display_pref_spec() as $name => $spec) {
            $prefs[$name] = $spec['val'];
        }
        if (function_exists('soo_plugin_pref_vals')) {
            $prefs = array_merge($prefs, soo_plugin_pref_vals('soo_plugin_display'));
        }
    }
    return $pref ? $prefs[$pref] : $prefs;
}

  //---------------------------------------------------------------------//
 //                                 Tags                                //
//---------------------------------------------------------------------//

    // tag to select plugin(s) for display
    // can be used as single w/form or container
    // for plugin lists code and help are not retrieved
function soo_plugin_display($atts, $thing = '')
{
    global $soo_plugin_display;
    extract(lAtts(array(
        'name'          =>  '',
        'prefix'        =>  '',
        'form'          =>  soo_plugin_display_prefs('default_form'),
        'show_inactive' =>  0,
        'sort'          =>  'name asc',
        'wraptag'       =>  '',
        'break'         =>  '',
        'class'         =>  '',
        'html_id'       =>  '',
    ), $atts));
    
    $columns = 'author, author_uri, version, description, name, '.
        'round(char_length(code)/1024, 1) as size'.( $name ? ', help, code' : '' );
        
    if ($name or $prefix) {
        $where = ( $name ? "name = '$name'" : "name like '$prefix%'" )
            .( ( $show_inactive or $name ) ? '' : ' and status = 1' );
    } else {
        $where = $show_inactive ? 1 : 'status = 1';
    }
    $where .= " order by $sort";
    
    if (! $data = safe_rows($columns, 'txp_plugin', $where)) return;
        
    foreach ($data as $r) {
        $soo_plugin_display = $r;
        $out[] = $thing ? parse($thing) : parse_form($form);
    }
    
    if (isset($out)) {
        return doWrap($out, $wraptag, $break, $class, '', '', '', $html_id);
    }
}

    // basic output tags: display field direct from database
function soo_plugin_author_uri() { return _soo_plugin_field('author_uri'); }
function soo_plugin_version() { return _soo_plugin_field('version'); }
function soo_plugin_description() { return _soo_plugin_field('description'); }

    // as above, but with option to make output a link to author's website
function soo_plugin_author($atts) { return _soo_plugin_field('author_uri', $atts); }
function soo_plugin_name($atts) { return _soo_plugin_field('name', $atts); }

    // display plugin help, with options to remove leading style and/or h1 elements,
    // and option to restrict display to named section
function soo_plugin_help($atts)
{ 
    $prefs = soo_plugin_display_prefs();
    extract(lAtts(array(
        'strip_style'   =>  $prefs['strip_style'],
        'strip_title'   =>  $prefs['strip_title'],
        'section_id'    =>  '',     // HTML id for header element to display
        'h_plus'        =>  0,      // transpose header levels by this amount
    ), $atts));
    $help = _soo_plugin_field('help');
    
        // remove leading <style> element
    if ($strip_style and preg_match('/^<style/', $help))
        $help = preg_replace('/^[\s\S]+?<\/style>([\s\S]+)$/', '$1', $help);
        
        // remove leading <h1> element
    if ($strip_title)
        $help = preg_replace('/^([\s\S]+?)<h1[\s\S]+?<\/h1>([\s\S]+)$/', '$1$2', $help);
    
        // display from named header element to next header with same or lower h-level
    if ($section_id) {
        $pattern = "/^([\s\S]+)<h(\d)([^>]+id=['\"]$section_id('|\").+?>[\s\S]+)$/";
        if (preg_match($pattern, $help, $match)) {
            list( , , $h_num, $remainder) = $match;
            $help = '';
            $pattern = '/^([\s\S]+?)<h(\d)([\s\S]+)$/';
            $remainder = "<h$h_num$remainder";
            while (preg_match($pattern, $remainder, $match)) {
                list( , $keep, $next_h, $remainder) = $match;
                $help .= $keep;
                $remainder = "<h$next_h$remainder";
                if ($h_num >= $next_h) $remainder = '';
            }
            if (! $help) $help = $remainder;
        }
    }
        
        // transpose HTML header levels.
    if ($h_plus) {
        for ($i = 1; $i <= 6; $i++) {
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
function soo_plugin_size($atts)
{
    extract(lAtts(array(
        'format'    =>  soo_plugin_display_prefs('size_format'),
    ), $atts));
    $size = _soo_plugin_field('size');
    return str_replace('{size}', $size, $format);
}

    // display plugin source code
    // code highlighting with named classes for styling
    // various options for line numbering
    // display full source or named function, class, or method
function soo_plugin_code($atts)
{
    $prefs = soo_plugin_display_prefs();
    extract(lAtts(array(
        'highlight'             =>  $prefs['highlight'],
        'show_line_numbers'     =>  $prefs['show_line_numbers'],
        'reindex_lines'         =>  0,
        'tab_stop'              =>  $prefs['tab_stop'],
        'function'              =>  '',
        'php_class'             =>  '',
        'class'                 =>  'soo_plugin_code',
        'html_id'               =>  '',
    ), $atts));
    
    $raw_code = trim(_soo_plugin_field('code'));
    $match_index = 2;
    $safety_check = true;
    
        // find named method: assumes end brace one tab in from start of line
    if ($php_class and $function) {
        $pattern = '/([\s\S]*(abstract|)\s*class\s+' 
            . $php_class . '\s+[\s\S]*?\{[\s\S]+?)(\t(public|private|protected)?\s*function\s+' 
            . $function . '\s*\([\s\S]+?\n\t}.*)[\s\S]*/';
        $match_index ++;
    }
    
        // find named function: assumes end brace at start of line
    elseif ($function)
        $pattern = '/([\s\S]*)((public|private|protected)?\s*function\s+'
            .$function.'\s*\([\s\S]+?\n}.*)/';
    
        // find named class: assumes end brace at start of line
    elseif ($php_class)
        $pattern = '/([\s\S]*?)((abstract|)\s*class\s+'
            .$php_class.'\s+[\s\S]*?\{[\s\S]+?\n}.*)[\s\S]*/';
    
    else $safety_check = false;
    
        // safety check is because the preg_match() can take a long time if there is no match
    if ($safety_check)
    {
        if ($function and ! preg_match("/$function\s*\(/", $raw_code)) return;
        if ($php_class and ! preg_match("/class\s*$php_class\s+/", $raw_code))
            return;
    }
    
    if (isset($pattern)) {
        if (! preg_match($pattern, $raw_code, $match)) return;
        $raw_code = $match[$match_index];
            // if not reindexing, find starting line number in full source code
        $start_line = $reindex_lines ? 
            $reindex_lines : count(explode(n, $match[1]));
    }
    
        // convert tabs to spaces, retaining tab stop alignment
    foreach (explode(n, $raw_code) as $line) {
        $bucket = array();
        foreach (str_split($line) as $char)
            if ($char == t) {
                $add = $tab_stop - fmod(count($bucket), $tab_stop);
                while ( $add ) {
                    $bucket[] = ' ';
                    $add --;
                }
            } else
                $bucket[] = $char;
        $lines[] = implode('', $bucket);    
    }
    
    if (! $highlight)
        return implode(n, array_map('htmlspecialchars', $lines));

    $find = array(sp, br, '<code>', '</code>');
    $replace = array(' ', n, '', '');
        // names of code highlight types from ini_get()
        // highlight_string() output style attributes will be replaced by named classes
    foreach (array('bg', 'comment', 'default', 'html', 'keyword', 'string') as $k) {
        $find[] = 'style="color: ' . ini_get("highlight.$k") . '"';
        $replace[] = "class=\"php_$k\"";
    }
    
    $start_line = 1;
    
        // run highlight_string(), clean up
    $h_s = highlight_string("<?php\n".implode(n, $lines)."\n?>", true);
    $lines = str_replace($find, $replace, explode(br, $h_s));
    array_shift($lines);
    $lines[0] = '<span class="php_html">' . $lines[0];
    array_pop($lines);
    $lines[count($lines)-1] .= '</span>';
    
        // add line numbers
    $total_lines = $start_line + count($lines) - 1;
    foreach ($lines as $i => $line) {
        if ($show_line_numbers) {
            $i += $start_line;
            $line = "<span class=\"php_comment\">$i $show_line_numbers</span> $line";
            if ( $i < 1000 and $total_lines > 999 ) $line = sp . $line;
            if ( $i < 100 and $total_lines > 99 ) $line = sp . $line;
            if ( $i < 10 ) $line = sp . $line;
        }
        $code[] = $line;
    }
    
    $tag_atts = ( $class ? " class=\"$class\"" : '' ).( $html_id ? " id=\"$html_id\"" : '' );
    
    return "<pre$tag_atts><code$tag_atts>".implode(n, $code).'</code></pre>';
}

  //---------------------------------------------------------------------//
 //                         Support Functions                           //
//---------------------------------------------------------------------//

function _soo_plugin_field($field, $atts = null)
{
    if (is_array($atts))
        extract(lAtts(array(
            'link'  =>  1,
        ), $atts));
    global $soo_plugin_display;
    $author_uri = $soo_plugin_display['author_uri'];
    if (isset($soo_plugin_display[$field])) {
        $out = $soo_plugin_display[$field];
        return ( ! empty($link) and $author_uri ) ? href($out, $author_uri) : $out;
    }
}

# --- END PLUGIN CODE ---

?>
