<?php

namespace Mpdf;

use fpdi_pdf_parser;
use pdf_parser;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;

use Mpdf\Css\TextVars;

use Mpdf\Image\ImageProcessor;

use Mpdf\Log\Context as LogContext;

use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\FontFileFinder;

use Mpdf\Output\Destination;

use Mpdf\Pdf\Protection;
use Mpdf\Pdf\Protection\UniqidGenerator;

use Mpdf\Utils\UtfString;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
/**
 * mPDF, PHP library generating PDF files from UTF-8 encoded HTML
 *
 * based on FPDF by Olivier Plathey
 *      and HTML2FPDF by Renato Coelho
 *
 * @version 7.0
 * @license GPL-2.0
 */
class Mpdf extends MpdfImpl
{
    public function cleanup()
    {
        mb_internal_encoding($this->originalMbEnc);
        @mb_regex_encoding($this->originalMbRegexEnc);
    }

    /**
     * @param \Psr\Log\LoggerInterface
     *
     * @return \Mpdf\Mpdf
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        foreach ($this->services as $name) {
            if ($this->$name && $this->$name instanceof \Psr\Log\LoggerAwareInterface) {
                $this->$name->setLogger($logger);
            }
        }

        return $this;
    }

    public function RestrictUnicodeFonts($res)
    {
        // $res = array of (Unicode) fonts to restrict to: e.g. norasi|norasiB - language specific
        if (count($res)) { // Leave full list of available fonts if passed blank array
            $this->available_unifonts = $res;
        } else {
            $this->available_unifonts = $this->default_available_fonts;
        }
        if (count($this->available_unifonts) == 0) {
            $this->available_unifonts[] = $this->default_available_fonts[0];
        }
        $this->available_unifonts = array_values($this->available_unifonts);
    }

    public function setMBencoding($enc)
    {
        if ($this->mb_enc != $enc) {
            $this->mb_enc = $enc;
            mb_internal_encoding($this->mb_enc);
        }
    }

    public function SetMargins($left, $right, $top)
    {
        // Set left, top and right margins
        $this->lMargin = $left;
        $this->rMargin = $right;
        $this->tMargin = $top;
    }

    public function ResetMargins()
    {
        // ReSet left, top margins
        if (($this->forcePortraitHeaders || $this->forcePortraitMargins) && $this->DefOrientation == 'P' && $this->CurOrientation == 'L') {
            if (($this->mirrorMargins) && (($this->page) % 2 == 0)) { // EVEN
                $this->tMargin = $this->orig_rMargin;
                $this->bMargin = $this->orig_lMargin;
            } else { // ODD // OR NOT MIRRORING MARGINS/FOOTERS
                $this->tMargin = $this->orig_lMargin;
                $this->bMargin = $this->orig_rMargin;
            }
            $this->lMargin = $this->DeflMargin;
            $this->rMargin = $this->DefrMargin;
            $this->MarginCorrection = 0;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
        } elseif (($this->mirrorMargins) && (($this->page) % 2 == 0)) { // EVEN
            $this->lMargin = $this->DefrMargin;
            $this->rMargin = $this->DeflMargin;
            $this->MarginCorrection = $this->DefrMargin - $this->DeflMargin;
        } else { // ODD // OR NOT MIRRORING MARGINS/FOOTERS
            $this->lMargin = $this->DeflMargin;
            $this->rMargin = $this->DefrMargin;
            if ($this->mirrorMargins) {
                $this->MarginCorrection = $this->DeflMargin - $this->DefrMargin;
            }
        }
        $this->x = $this->lMargin;
    }

    public function SetLeftMargin($margin)
    {
        // Set left margin
        $this->lMargin = $margin;
        if ($this->page > 0 and $this->x < $margin) {
            $this->x = $margin;
        }
    }

    public function SetTopMargin($margin)
    {
        // Set top margin
        $this->tMargin = $margin;
    }

    public function SetRightMargin($margin)
    {
        // Set right margin
        $this->rMargin = $margin;
    }

    public function SetAutoPageBreak($auto, $margin = 0)
    {
        // Set auto page break mode and triggering margin
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetDisplayMode($zoom, $layout = 'continuous')
    {
        // Set display mode in viewer
        if ($zoom == 'fullpage' or $zoom == 'fullwidth' or $zoom == 'real' or $zoom == 'default' or ! is_string($zoom)) {
            $this->ZoomMode = $zoom;
        } else {
            throw new \Mpdf\MpdfException('Incorrect zoom display mode: ' . $zoom);
        }
        if ($layout == 'single' or $layout == 'continuous' or $layout == 'two' or $layout == 'twoleft' or $layout == 'tworight' or $layout == 'default') {
            $this->LayoutMode = $layout;
        } else {
            throw new \Mpdf\MpdfException('Incorrect layout display mode: ' . $layout);
        }
    }

    public function SetCompression($compress)
    {
        // Set page compression
        if (function_exists('gzcompress')) {
            $this->compress = $compress;
        } else {
            $this->compress = false;
        }
    }

    public function SetTitle($title)
    {
        // Title of document // Arrives as UTF-8
        $this->title = $title;
    }

    public function SetSubject($subject)
    {
        // Subject of document
        $this->subject = $subject;
    }

    public function SetAuthor($author)
    {
        // Author of document
        $this->author = $author;
    }

    public function SetKeywords($keywords)
    {
        // Keywords of document
        $this->keywords = $keywords;
    }

    public function SetCreator($creator)
    {
        // Creator of document
        $this->creator = $creator;
    }

    public function AddCustomProperty($key, $value)
    {
        $this->customProperties[$key] = $value;
    }

    /**
     * Set one or multiple associated file ("/AF" as required by PDF/A-3)
     *
     * param $files is an array of hash containing:
     *   path: file path on FS
     *   content: file content
     *   name: file name (not necessarily the same as the file on FS)
     *   mime (optional): file mime type (will show up as /Subtype in the PDF)
     *   description (optional): file description
     *   AFRelationship (optional): PDF/A-3 AFRelationship (e.g. "Alternative")
     *
     * e.g. to associate 1 file:
     *     [[
     *         'path' => 'tmp/1234.xml',
     *         'content' => 'file content',
     *         'name' => 'public_name.xml',
     *         'mime' => 'text/xml',
     *         'description' => 'foo',
     *         'AFRelationship' => 'Alternative',
     *     ]]
     *
     * @param mixed[] $files Array of arrays of associated files. See above
     */
    public function SetAssociatedFiles(array $files)
    {
        $this->associatedFiles = $files;
    }

    public function SetAdditionalXmpRdf($s)
    {
        $this->additionalXmpRdf = $s;
    }

    public function SetAnchor2Bookmark($x)
    {
        $this->anchor2Bookmark = $x;
    }

    public function AliasNbPages($alias = '{nb}')
    {
        // Define an alias for total number of pages
        $this->aliasNbPg = $alias;
    }

    public function AliasNbPageGroups($alias = '{nbpg}')
    {
        // Define an alias for total number of pages in a group
        $this->aliasNbPgGp = $alias;
    }

    public function SetAlpha($alpha, $bm = 'Normal', $return = false, $mode = 'B')
    {
        // alpha: real value from 0 (transparent) to 1 (opaque)
        // bm:    blend mode, one of the following:
        //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
        //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
        // set alpha for stroking (CA) and non-stroking (ca) operations
        // mode determines F (fill) S (stroke) B (both)
        if (($this->PDFA || $this->PDFX) && $alpha != 1) {
            if (($this->PDFA && !$this->PDFAauto) || ($this->PDFX && !$this->PDFXauto)) {
                $this->PDFAXwarnings[] = "Image opacity must be 100% (Opacity changed to 100%)";
            }
            $alpha = 1;
        }
        $a = ['BM' => '/' . $bm];
        if ($mode == 'F' || $mode == 'B') {
            $a['ca'] = $alpha; // mPDF 5.7.2
        }
        if ($mode == 'S' || $mode == 'B') {
            $a['CA'] = $alpha; // mPDF 5.7.2
        }
        $gs = $this->AddExtGState($a);
        if ($return) {
            return sprintf('/GS%d gs', $gs);
        } else {
            $this->_out(sprintf('/GS%d gs', $gs));
        }
    }

    public function AddExtGState($parms)
    {
        $n = count($this->extgstates);
        // check if graphics state already exists
        for ($i = 1; $i <= $n; $i++) {
            if (count($this->extgstates[$i]['parms']) == count($parms)) {
                $same = true;
                foreach ($this->extgstates[$i]['parms'] as $k => $v) {
                    if (!isset($parms[$k]) || $parms[$k] != $v) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    return $i;
                }
            }
        }
        $n++;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    public function SetVisibility($v)
    {
        if (($this->PDFA || $this->PDFX) && $this->visibility != 'visible') {
            $this->PDFAXwarnings[] = "Cannot set visibility to anything other than full when using PDFA or PDFX";
            return '';
        } elseif (!$this->PDFA && !$this->PDFX) {
            $this->pdf_version = '1.5';
        }
        if ($this->visibility != 'visible') {
            $this->_out('EMC');
            $this->hasOC = intval($this->hasOC);
        }
        if ($v == 'printonly') {
            $this->_out('/OC /OC1 BDC');
            $this->hasOC = ($this->hasOC | 1);
        } elseif ($v == 'screenonly') {
            $this->_out('/OC /OC2 BDC');
            $this->hasOC = ($this->hasOC | 2);
        } elseif ($v == 'hidden') {
            $this->_out('/OC /OC3 BDC');
            $this->hasOC = ($this->hasOC | 4);
        } elseif ($v != 'visible') {
            throw new \Mpdf\MpdfException('Incorrect visibility: ' . $v);
        }
        $this->visibility = $v;
    }

    public function Open()
    {
        // Begin document
        if ($this->state == 0) {
            // Was is function _begindoc()
            // Start document
            $this->state = 1;
            $this->_out('%PDF-' . $this->pdf_version);
            $this->_out('%' . chr(226) . chr(227) . chr(207) . chr(211)); // 4 chars > 128 to show binary file
        }
    }

    public function Close()
    {
        // @log Closing last page

        // Terminate document
        if ($this->state == 3) {
            return;
        }
        if ($this->page == 0) {
            $this->AddPage($this->CurOrientation);
        }
        if (count($this->cellBorderBuffer)) {
            $this->printcellbuffer();
        } // *TABLES*
        if ($this->tablebuffer) {
            $this->printtablebuffer();
        } // *TABLES*
        /* -- COLUMNS -- */

        if ($this->ColActive) {
            $this->SetColumns(0);
            $this->ColActive = 0;
            if (count($this->columnbuffer)) {
                $this->printcolumnbuffer();
            }
        }
        /* -- END COLUMNS -- */

        // BODY Backgrounds
        $s = '';

        $s .= $this->PrintBodyBackgrounds();
        $s .= $this->PrintPageBackgrounds();

        $this->pages[$this->page] = preg_replace(
            '/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/',
            "\n" . $s . "\n" . '\\1',
            $this->pages[$this->page]
        );

        $this->pageBackgrounds = [];

        if ($this->visibility != 'visible') {
            $this->SetVisibility('visible');
        }

        $this->EndLayer();

        if (!$this->tableOfContents->TOCmark) { // Page footer
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
        }

        if ($this->tableOfContents->TOCmark || count($this->tableOfContents->m_TOC)) {
            $this->tableOfContents->insertTOC();
        }

        // *TOC*
        // Close page
        $this->_endpage();

        // Close document
        $this->_enddoc();
    }

    /* -- BACKGROUNDS -- */

    public function SetBackground(&$properties, &$maxwidth)
    {
        if (isset($properties['BACKGROUND-ORIGIN']) && ($properties['BACKGROUND-ORIGIN'] == 'border-box' || $properties['BACKGROUND-ORIGIN'] == 'content-box')) {
            $origin = $properties['BACKGROUND-ORIGIN'];
        } else {
            $origin = 'padding-box';
        }

        if (isset($properties['BACKGROUND-SIZE'])) {
            if (stristr($properties['BACKGROUND-SIZE'], 'contain')) {
                $bsw = $bsh = 'contain';
            } elseif (stristr($properties['BACKGROUND-SIZE'], 'cover')) {
                $bsw = $bsh = 'cover';
            } else {
                $bsw = $bsh = 'auto';
                $sz = preg_split('/\s+/', trim($properties['BACKGROUND-SIZE']));
                if (count($sz) == 2) {
                    $bsw = $sz[0];
                    $bsh = $sz[1];
                } else {
                    $bsw = $sz[0];
                }
                if (!stristr($bsw, '%') && !stristr($bsw, 'auto')) {
                    $bsw = $this->sizeConverter->convert($bsw, $maxwidth, $this->FontSize);
                }
                if (!stristr($bsh, '%') && !stristr($bsh, 'auto')) {
                    $bsh = $this->sizeConverter->convert($bsh, $maxwidth, $this->FontSize);
                }
            }
            $size = ['w' => $bsw, 'h' => $bsh];
        } else {
            $size = false;
        } // mPDF 6
        if (preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $properties['BACKGROUND-IMAGE'])) {
            return ['gradient' => $properties['BACKGROUND-IMAGE'], 'origin' => $origin, 'size' => $size];
        } else {
            $file = $properties['BACKGROUND-IMAGE'];
            $sizesarray = $this->Image($file, 0, 0, 0, 0, '', '', false, false, false, false, true);
            if (isset($sizesarray['IMAGE_ID'])) {
                $image_id = $sizesarray['IMAGE_ID'];
                $orig_w = $sizesarray['WIDTH'] * Mpdf::SCALE;  // in user units i.e. mm
                $orig_h = $sizesarray['HEIGHT'] * Mpdf::SCALE;  // (using $this->img_dpi)
                if (isset($properties['BACKGROUND-IMAGE-RESOLUTION'])) {
                    if (preg_match('/from-image/i', $properties['BACKGROUND-IMAGE-RESOLUTION']) && isset($sizesarray['set-dpi']) && $sizesarray['set-dpi'] > 0) {
                        $orig_w *= $this->img_dpi / $sizesarray['set-dpi'];
                        $orig_h *= $this->img_dpi / $sizesarray['set-dpi'];
                    } elseif (preg_match('/(\d+)dpi/i', $properties['BACKGROUND-IMAGE-RESOLUTION'], $m)) {
                        $dpi = $m[1];
                        if ($dpi > 0) {
                            $orig_w *= $this->img_dpi / $dpi;
                            $orig_h *= $this->img_dpi / $dpi;
                        }
                    }
                }
                $x_repeat = true;
                $y_repeat = true;
                if (isset($properties['BACKGROUND-REPEAT'])) {
                    if ($properties['BACKGROUND-REPEAT'] == 'no-repeat' || $properties['BACKGROUND-REPEAT'] == 'repeat-x') {
                        $y_repeat = false;
                    }
                    if ($properties['BACKGROUND-REPEAT'] == 'no-repeat' || $properties['BACKGROUND-REPEAT'] == 'repeat-y') {
                        $x_repeat = false;
                    }
                }
                $x_pos = 0;
                $y_pos = 0;
                if (isset($properties['BACKGROUND-POSITION'])) {
                    $ppos = preg_split('/\s+/', $properties['BACKGROUND-POSITION']);
                    $x_pos = $ppos[0];
                    $y_pos = $ppos[1];
                    if (!stristr($x_pos, '%')) {
                        $x_pos = $this->sizeConverter->convert($x_pos, $maxwidth, $this->FontSize);
                    }
                    if (!stristr($y_pos, '%')) {
                        $y_pos = $this->sizeConverter->convert($y_pos, $maxwidth, $this->FontSize);
                    }
                }
                if (isset($properties['BACKGROUND-IMAGE-RESIZE'])) {
                    $resize = $properties['BACKGROUND-IMAGE-RESIZE'];
                } else {
                    $resize = 0;
                }
                if (isset($properties['BACKGROUND-IMAGE-OPACITY'])) {
                    $opacity = $properties['BACKGROUND-IMAGE-OPACITY'];
                } else {
                    $opacity = 1;
                }
                return ['image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'resize' => $resize, 'opacity' => $opacity, 'itype' => $sizesarray['itype'], 'origin' => $origin, 'size' => $size];
            }
        }
        return false;
    }

    /* -- END BACKGROUNDS -- */

    public function PrintBodyBackgrounds()
    {
        $s = '';
        $clx = 0;
        $cly = 0;
        $clw = $this->w;
        $clh = $this->h;
        // If using bleed and trim margins in paged media
        if ($this->pageDim[$this->page]['outer_width_LR'] || $this->pageDim[$this->page]['outer_width_TB']) {
            $clx = $this->pageDim[$this->page]['outer_width_LR'] - $this->pageDim[$this->page]['bleedMargin'];
            $cly = $this->pageDim[$this->page]['outer_width_TB'] - $this->pageDim[$this->page]['bleedMargin'];
            $clw = $this->w - 2 * $clx;
            $clh = $this->h - 2 * $cly;
        }

        if ($this->bodyBackgroundColor) {
            $s .= 'q ' . $this->SetFColor($this->bodyBackgroundColor, true) . "\n";
            if ($this->bodyBackgroundColor{0} == 5) { // RGBa
                $s .= $this->SetAlpha(ord($this->bodyBackgroundColor{4}) / 100, 'Normal', true, 'F') . "\n";
            } elseif ($this->bodyBackgroundColor{0} == 6) { // CMYKa
                $s .= $this->SetAlpha(ord($this->bodyBackgroundColor{5}) / 100, 'Normal', true, 'F') . "\n";
            }
            $s .= sprintf('%.3F %.3F %.3F %.3F re f Q', ($clx * Mpdf::SCALE), ($cly * Mpdf::SCALE), $clw * Mpdf::SCALE, $clh * Mpdf::SCALE) . "\n";
        }

        /* -- BACKGROUNDS -- */
        if ($this->bodyBackgroundGradient) {
            $g = $this->gradient->parseBackgroundGradient($this->bodyBackgroundGradient);
            if ($g) {
                $s .= $this->gradient->Gradient($clx, $cly, $clw, $clh, (isset($g['gradtype']) ? $g['gradtype'] : null), $g['stops'], $g['colorspace'], $g['coords'], $g['extend'], true);
            }
        }
        if ($this->bodyBackgroundImage) {
            if (isset($this->bodyBackgroundImage['gradient']) && $this->bodyBackgroundImage['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $this->bodyBackgroundImage['gradient'])) {
                $g = $this->gradient->parseMozGradient($this->bodyBackgroundImage['gradient']);
                if ($g) {
                    $s .= $this->gradient->Gradient($clx, $cly, $clw, $clh, $g['type'], $g['stops'], $g['colorspace'], $g['coords'], $g['extend'], true);
                }
            } elseif ($this->bodyBackgroundImage['image_id']) { // Background pattern
                $n = count($this->patterns) + 1;
                // If using resize, uses TrimBox (not including the bleed)
                list($orig_w, $orig_h, $x_repeat, $y_repeat) = $this->_resizeBackgroundImage($this->bodyBackgroundImage['orig_w'], $this->bodyBackgroundImage['orig_h'], $clw, $clh, $this->bodyBackgroundImage['resize'], $this->bodyBackgroundImage['x_repeat'], $this->bodyBackgroundImage['y_repeat']);

                $this->patterns[$n] = ['x' => $clx, 'y' => $cly, 'w' => $clw, 'h' => $clh, 'pgh' => $this->h, 'image_id' => $this->bodyBackgroundImage['image_id'], 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $this->bodyBackgroundImage['x_pos'], 'y_pos' => $this->bodyBackgroundImage['y_pos'], 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'itype' => $this->bodyBackgroundImage['itype']];
                if (($this->bodyBackgroundImage['opacity'] > 0 || $this->bodyBackgroundImage['opacity'] === '0') && $this->bodyBackgroundImage['opacity'] < 1) {
                    $opac = $this->SetAlpha($this->bodyBackgroundImage['opacity'], 'Normal', true);
                } else {
                    $opac = '';
                }
                $s .= sprintf('q /Pattern cs /P%d scn %s %.3F %.3F %.3F %.3F re f Q', $n, $opac, ($clx * Mpdf::SCALE), ($cly * Mpdf::SCALE), $clw * Mpdf::SCALE, $clh * Mpdf::SCALE) . "\n";
            }
        }
        /* -- END BACKGROUNDS -- */
        return $s;
    }

    public function PrintPageBackgrounds($adjustmenty = 0)
    {
        $s = '';

        ksort($this->pageBackgrounds);
        foreach ($this->pageBackgrounds as $bl => $pbs) {
            foreach ($pbs as $pb) {
                if ((!isset($pb['image_id']) && !isset($pb['gradient'])) || isset($pb['shadowonly'])) { // Background colour or boxshadow
                    if ($pb['z-index'] > 0) {
                        $this->current_layer = $pb['z-index'];
                        $s .= "\n" . '/OCBZ-index /ZI' . $pb['z-index'] . ' BDC' . "\n";
                    }

                    if ($pb['visibility'] != 'visible') {
                        if ($pb['visibility'] == 'printonly') {
                            $s .= '/OC /OC1 BDC' . "\n";
                        } elseif ($pb['visibility'] == 'screenonly') {
                            $s .= '/OC /OC2 BDC' . "\n";
                        } elseif ($pb['visibility'] == 'hidden') {
                            $s .= '/OC /OC3 BDC' . "\n";
                        }
                    }
                    // Box shadow
                    if (isset($pb['shadow']) && $pb['shadow']) {
                        $s .= $pb['shadow'] . "\n";
                    }
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= $pb['clippath'] . "\n";
                    }
                    $s .= 'q ' . $this->SetFColor($pb['col'], true) . "\n";
                    if ($pb['col']{0} == 5) { // RGBa
                        $s .= $this->SetAlpha(ord($pb['col']{4}) / 100, 'Normal', true, 'F') . "\n";
                    } elseif ($pb['col']{0} == 6) { // CMYKa
                        $s .= $this->SetAlpha(ord($pb['col']{5}) / 100, 'Normal', true, 'F') . "\n";
                    }
                    $s .= sprintf('%.3F %.3F %.3F %.3F re f Q', $pb['x'] * Mpdf::SCALE, ($this->h - $pb['y']) * Mpdf::SCALE, $pb['w'] * Mpdf::SCALE, -$pb['h'] * Mpdf::SCALE) . "\n";
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= 'Q' . "\n";
                    }
                    if ($pb['visibility'] != 'visible') {
                        $s .= 'EMC' . "\n";
                    }

                    if ($pb['z-index'] > 0) {
                        $s .= "\n" . 'EMCBZ-index' . "\n";
                        $this->current_layer = 0;
                    }
                }
            }
            /* -- BACKGROUNDS -- */
            foreach ($pbs as $pb) {
                if ((isset($pb['gradient']) && $pb['gradient']) || (isset($pb['image_id']) && $pb['image_id'])) {
                    if ($pb['z-index'] > 0) {
                        $this->current_layer = $pb['z-index'];
                        $s .= "\n" . '/OCGZ-index /ZI' . $pb['z-index'] . ' BDC' . "\n";
                    }
                    if ($pb['visibility'] != 'visible') {
                        if ($pb['visibility'] == 'printonly') {
                            $s .= '/OC /OC1 BDC' . "\n";
                        } elseif ($pb['visibility'] == 'screenonly') {
                            $s .= '/OC /OC2 BDC' . "\n";
                        } elseif ($pb['visibility'] == 'hidden') {
                            $s .= '/OC /OC3 BDC' . "\n";
                        }
                    }
                }
                if (isset($pb['gradient']) && $pb['gradient']) {
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= $pb['clippath'] . "\n";
                    }
                    $s .= $this->gradient->Gradient($pb['x'], $pb['y'], $pb['w'], $pb['h'], $pb['gradtype'], $pb['stops'], $pb['colorspace'], $pb['coords'], $pb['extend'], true);
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= 'Q' . "\n";
                    }
                } elseif (isset($pb['image_id']) && $pb['image_id']) { // Background Image
                    $pb['y'] -= $adjustmenty;
                    $pb['h'] += $adjustmenty;
                    $n = count($this->patterns) + 1;
                    list($orig_w, $orig_h, $x_repeat, $y_repeat) = $this->_resizeBackgroundImage($pb['orig_w'], $pb['orig_h'], $pb['w'], $pb['h'], $pb['resize'], $pb['x_repeat'], $pb['y_repeat'], $pb['bpa'], $pb['size']);
                    $this->patterns[$n] = ['x' => $pb['x'], 'y' => $pb['y'], 'w' => $pb['w'], 'h' => $pb['h'], 'pgh' => $this->h, 'image_id' => $pb['image_id'], 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $pb['x_pos'], 'y_pos' => $pb['y_pos'], 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'itype' => $pb['itype'], 'bpa' => $pb['bpa']];
                    $x = $pb['x'] * Mpdf::SCALE;
                    $y = ($this->h - $pb['y']) * Mpdf::SCALE;
                    $w = $pb['w'] * Mpdf::SCALE;
                    $h = -$pb['h'] * Mpdf::SCALE;
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= $pb['clippath'] . "\n";
                    }
                    if ($this->writingHTMLfooter || $this->writingHTMLheader) { // Write each (tiles) image rather than use as a pattern
                        $iw = $pb['orig_w'] / Mpdf::SCALE;
                        $ih = $pb['orig_h'] / Mpdf::SCALE;

                        $w = $pb['w'];
                        $h = $pb['h'];
                        $x0 = $pb['x'];
                        $y0 = $pb['y'];

                        if (isset($pb['bpa']) && $pb['bpa']) {
                            $w = $pb['bpa']['w'];
                            $h = $pb['bpa']['h'];
                            $x0 = $pb['bpa']['x'];
                            $y0 = $pb['bpa']['y'];
                        }

                        if (isset($pb['size']['w']) && $pb['size']['w']) {
                            $size = $pb['size'];

                            if ($size['w'] == 'contain') {
                                // Scale the image, while preserving its intrinsic aspect ratio (if any), to the largest
                                // size such that both its width and its height can fit inside the background positioning area.
                                // Same as resize==3
                                $ih = $ih * $pb['bpa']['w'] / $iw;
                                $iw = $pb['bpa']['w'];
                                if ($ih > $pb['bpa']['h']) {
                                    $iw = $iw * $pb['bpa']['h'] / $ih;
                                    $ih = $pb['bpa']['h'];
                                }
                            } elseif ($size['w'] == 'cover') {
                                // Scale the image, while preserving its intrinsic aspect ratio (if any), to the smallest
                                // size such that both its width and its height can completely cover the background positioning area.
                                $ih = $ih * $pb['bpa']['w'] / $iw;
                                $iw = $pb['bpa']['w'];
                                if ($ih < $pb['bpa']['h']) {
                                    $iw = $iw * $ih / $pb['bpa']['h'];
                                    $ih = $pb['bpa']['h'];
                                }
                            } else {
                                if (NumericString::containsPercentChar($size['w'])) {
                                    $size['w'] = NumericString::removePercentChar($size['w']);
                                    $size['w'] /= 100;
                                    $size['w'] = ($pb['bpa']['w'] * $size['w']);
                                }
                                if (NumericString::containsPercentChar($size['h'])) {
                                    $size['h'] = NumericString::removePercentChar($size['h']);
                                    $size['h'] /= 100;
                                    $size['h'] = ($pb['bpa']['h'] * $size['h']);
                                }
                                if ($size['w'] == 'auto' && $size['h'] == 'auto') {
                                    $iw = $iw;
                                    $ih = $ih;
                                } elseif ($size['w'] == 'auto' && $size['h'] != 'auto') {
                                    $iw = $iw * $size['h'] / $ih;
                                    $ih = $size['h'];
                                } elseif ($size['w'] != 'auto' && $size['h'] == 'auto') {
                                    $ih = $ih * $size['w'] / $iw;
                                    $iw = $size['w'];
                                } else {
                                    $iw = $size['w'];
                                    $ih = $size['h'];
                                }
                            }
                        }

                        // Number to repeat
                        if ($pb['x_repeat']) {
                            $nx = ceil($pb['w'] / $iw) + 1;
                        } else {
                            $nx = 1;
                        }

                        if ($pb['y_repeat']) {
                            $ny = ceil($pb['h'] / $ih) + 1;
                        } else {
                            $ny = 1;
                        }

                        $x_pos = $pb['x_pos'];
                        if (stristr($x_pos, '%')) {
                            $x_pos = (float) $x_pos;
                            $x_pos /= 100;
                            $x_pos = ($pb['bpa']['w'] * $x_pos) - ($iw * $x_pos);
                        }

                        $y_pos = $pb['y_pos'];
                        if (stristr($y_pos, '%')) {
                            $y_pos = (float) $y_pos;
                            $y_pos /= 100;
                            $y_pos = ($pb['bpa']['h'] * $y_pos) - ($ih * $y_pos);
                        }
                        if ($nx > 1) {
                            while ($x_pos > ($pb['x'] - $pb['bpa']['x'])) {
                                $x_pos -= $iw;
                            }
                        }

                        if ($ny > 1) {
                            while ($y_pos > ($pb['y'] - $pb['bpa']['y'])) {
                                $y_pos -= $ih;
                            }
                        }

                        for ($xi = 0; $xi < $nx; $xi++) {
                            for ($yi = 0; $yi < $ny; $yi++) {
                                $x = $x0 + $x_pos + ($iw * $xi);
                                $y = $y0 + $y_pos + ($ih * $yi);
                                if ($pb['opacity'] > 0 && $pb['opacity'] < 1) {
                                    $opac = $this->SetAlpha($pb['opacity'], 'Normal', true);
                                } else {
                                    $opac = '';
                                }
                                $s .= sprintf("q %s %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q", $opac, $iw * Mpdf::SCALE, $ih * Mpdf::SCALE, $x * Mpdf::SCALE, ($this->h - ($y + $ih)) * Mpdf::SCALE, $pb['image_id']) . "\n";
                            }
                        }
                    } else {
                        if (($pb['opacity'] > 0 || $pb['opacity'] === '0') && $pb['opacity'] < 1) {
                            $opac = $this->SetAlpha($pb['opacity'], 'Normal', true);
                        } else {
                            $opac = '';
                        }
                        $s .= sprintf('q /Pattern cs /P%d scn %s %.3F %.3F %.3F %.3F re f Q', $n, $opac, $x, $y, $w, $h) . "\n";
                    }

                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= 'Q' . "\n";
                    }
                }

                if ((isset($pb['gradient']) && $pb['gradient']) || (isset($pb['image_id']) && $pb['image_id'])) {
                    if ($pb['visibility'] != 'visible') {
                        $s .= 'EMC' . "\n";
                    }

                    if ($pb['z-index'] > 0) {
                        $s .= "\n" . 'EMCGZ-index' . "\n";
                        $this->current_layer = 0;
                    }
                }
            }
            /* -- END BACKGROUNDS -- */
        }
        return $s;
    }

    public function PrintTableBackgrounds($adjustmenty = 0)
    {
        $s = '';
        /* -- BACKGROUNDS -- */
        ksort($this->tableBackgrounds);
        foreach ($this->tableBackgrounds as $bl => $pbs) {
            foreach ($pbs as $pb) {
                if ((!isset($pb['gradient']) || !$pb['gradient']) && (!isset($pb['image_id']) || !$pb['image_id'])) {
                    $s .= 'q ' . $this->SetFColor($pb['col'], true) . "\n";
                    if ($pb['col']{0} == 5) { // RGBa
                        $s .= $this->SetAlpha(ord($pb['col']{4}) / 100, 'Normal', true, 'F') . "\n";
                    } elseif ($pb['col']{0} == 6) { // CMYKa
                        $s .= $this->SetAlpha(ord($pb['col']{5}) / 100, 'Normal', true, 'F') . "\n";
                    }
                    $s .= sprintf('%.3F %.3F %.3F %.3F re %s Q', $pb['x'] * Mpdf::SCALE, ($this->h - $pb['y']) * Mpdf::SCALE, $pb['w'] * Mpdf::SCALE, -$pb['h'] * Mpdf::SCALE, 'f') . "\n";
                }
                if (isset($pb['gradient']) && $pb['gradient']) {
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= $pb['clippath'] . "\n";
                    }
                    $s .= $this->gradient->Gradient($pb['x'], $pb['y'], $pb['w'], $pb['h'], $pb['gradtype'], $pb['stops'], $pb['colorspace'], $pb['coords'], $pb['extend'], true);
                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= 'Q' . "\n";
                    }
                }
                if (isset($pb['image_id']) && $pb['image_id']) { // Background pattern
                    $pb['y'] -= $adjustmenty;
                    $pb['h'] += $adjustmenty;
                    $n = count($this->patterns) + 1;
                    list($orig_w, $orig_h, $x_repeat, $y_repeat) = $this->_resizeBackgroundImage($pb['orig_w'], $pb['orig_h'], $pb['w'], $pb['h'], $pb['resize'], $pb['x_repeat'], $pb['y_repeat']);
                    $this->patterns[$n] = ['x' => $pb['x'], 'y' => $pb['y'], 'w' => $pb['w'], 'h' => $pb['h'], 'pgh' => $this->h, 'image_id' => $pb['image_id'], 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $pb['x_pos'], 'y_pos' => $pb['y_pos'], 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'itype' => $pb['itype']];
                    $x = $pb['x'] * Mpdf::SCALE;
                    $y = ($this->h - $pb['y']) * Mpdf::SCALE;
                    $w = $pb['w'] * Mpdf::SCALE;
                    $h = -$pb['h'] * Mpdf::SCALE;

                    // mPDF 5.7.3
                    if (($this->writingHTMLfooter || $this->writingHTMLheader) && (!isset($pb['clippath']) || $pb['clippath'] == '')) {
                        // Set clipping path
                        $pb['clippath'] = sprintf(' q 0 w %.3F %.3F m %.3F %.3F l %.3F %.3F l %.3F %.3F l %.3F %.3F l W n ', $x, $y, $x, $y + $h, $x + $w, $y + $h, $x + $w, $y, $x, $y);
                    }

                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= $pb['clippath'] . "\n";
                    }

                    // mPDF 5.7.3
                    if ($this->writingHTMLfooter || $this->writingHTMLheader) { // Write each (tiles) image rather than use as a pattern
                        $iw = $pb['orig_w'] / Mpdf::SCALE;
                        $ih = $pb['orig_h'] / Mpdf::SCALE;

                        $w = $pb['w'];
                        $h = $pb['h'];
                        $x0 = $pb['x'];
                        $y0 = $pb['y'];

                        if (isset($pb['bpa']) && $pb['bpa']) {
                            $w = $pb['bpa']['w'];
                            $h = $pb['bpa']['h'];
                            $x0 = $pb['bpa']['x'];
                            $y0 = $pb['bpa']['y'];
                        } // At present 'bpa' (background page area) is not set for tablebackgrounds - only pagebackgrounds
                        // For now, just set it as:
                        else {
                            $pb['bpa'] = ['x' => $x0, 'y' => $y0, 'w' => $w, 'h' => $h];
                        }

                        if (isset($pb['size']['w']) && $pb['size']['w']) {
                            $size = $pb['size'];

                            if ($size['w'] == 'contain') {
                                // Scale the image, while preserving its intrinsic aspect ratio (if any), to the largest size such that both its width and its height can fit inside the background positioning area.
                                // Same as resize==3
                                $ih = $ih * $pb['bpa']['w'] / $iw;
                                $iw = $pb['bpa']['w'];
                                if ($ih > $pb['bpa']['h']) {
                                    $iw = $iw * $pb['bpa']['h'] / $ih;
                                    $ih = $pb['bpa']['h'];
                                }
                            } elseif ($size['w'] == 'cover') {
                                // Scale the image, while preserving its intrinsic aspect ratio (if any), to the smallest size such that both its width and its height can completely cover the background positioning area.
                                $ih = $ih * $pb['bpa']['w'] / $iw;
                                $iw = $pb['bpa']['w'];
                                if ($ih < $pb['bpa']['h']) {
                                    $iw = $iw * $ih / $pb['bpa']['h'];
                                    $ih = $pb['bpa']['h'];
                                }
                            } else {
                                if (NumericString::containsPercentChar($size['w'])) {
                                    $size['w'] = NumericString::removePercentChar($size['w']);
                                    $size['w'] /= 100;
                                    $size['w'] = ($pb['bpa']['w'] * $size['w']);
                                }
                                if (NumericString::containsPercentChar($size['h'])) {
                                    $size['h'] = NumericString::removePercentChar($size['h']);
                                    $size['h'] /= 100;
                                    $size['h'] = ($pb['bpa']['h'] * $size['h']);
                                }
                                if ($size['w'] == 'auto' && $size['h'] == 'auto') {
                                    $iw = $iw;
                                    $ih = $ih;
                                } elseif ($size['w'] == 'auto' && $size['h'] != 'auto') {
                                    $iw = $iw * $size['h'] / $ih;
                                    $ih = $size['h'];
                                } elseif ($size['w'] != 'auto' && $size['h'] == 'auto') {
                                    $ih = $ih * $size['w'] / $iw;
                                    $iw = $size['w'];
                                } else {
                                    $iw = $size['w'];
                                    $ih = $size['h'];
                                }
                            }
                        }

                        // Number to repeat
                        if (isset($pb['x_repeat']) && $pb['x_repeat']) {
                            $nx = ceil($pb['w'] / $iw) + 1;
                        } else {
                            $nx = 1;
                        }
                        if (isset($pb['y_repeat']) && $pb['y_repeat']) {
                            $ny = ceil($pb['h'] / $ih) + 1;
                        } else {
                            $ny = 1;
                        }

                        $x_pos = $pb['x_pos'];
                        if (NumericString::containsPercentChar($x_pos)) {
                            $x_pos = NumericString::removePercentChar($x_pos);
                            $x_pos /= 100;
                            $x_pos = ($pb['bpa']['w'] * $x_pos) - ($iw * $x_pos);
                        }
                        $y_pos = $pb['y_pos'];
                        if (NumericString::containsPercentChar($y_pos)) {
                            $y_pos = NumericString::removePercentChar($y_pos);
                            $y_pos /= 100;
                            $y_pos = ($pb['bpa']['h'] * $y_pos) - ($ih * $y_pos);
                        }
                        if ($nx > 1) {
                            while ($x_pos > ($pb['x'] - $pb['bpa']['x'])) {
                                $x_pos -= $iw;
                            }
                        }
                        if ($ny > 1) {
                            while ($y_pos > ($pb['y'] - $pb['bpa']['y'])) {
                                $y_pos -= $ih;
                            }
                        }
                        for ($xi = 0; $xi < $nx; $xi++) {
                            for ($yi = 0; $yi < $ny; $yi++) {
                                $x = $x0 + $x_pos + ($iw * $xi);
                                $y = $y0 + $y_pos + ($ih * $yi);
                                if ($pb['opacity'] > 0 && $pb['opacity'] < 1) {
                                    $opac = $this->SetAlpha($pb['opacity'], 'Normal', true);
                                } else {
                                    $opac = '';
                                }
                                $s .= sprintf("q %s %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q", $opac, $iw * Mpdf::SCALE, $ih * Mpdf::SCALE, $x * Mpdf::SCALE, ($this->h - ($y + $ih)) * Mpdf::SCALE, $pb['image_id']) . "\n";
                            }
                        }
                    } else {
                        if (($pb['opacity'] > 0 || $pb['opacity'] === '0') && $pb['opacity'] < 1) {
                            $opac = $this->SetAlpha($pb['opacity'], 'Normal', true);
                        } else {
                            $opac = '';
                        }
                        $s .= sprintf('q /Pattern cs /P%d scn %s %.3F %.3F %.3F %.3F re f Q', $n, $opac, $x, $y, $w, $h) . "\n";
                    }

                    if (isset($pb['clippath']) && $pb['clippath']) {
                        $s .= 'Q' . "\n";
                    }
                }
            }
        }
        /* -- END BACKGROUNDS -- */
        return $s;
    }

    public function BeginLayer($id)
    {
        if ($this->current_layer > 0) {
            $this->EndLayer();
        }
        if ($id < 1) {
            return false;
        }
        if (!isset($this->layers[$id])) {
            $this->layers[$id] = ['name' => 'Layer ' . ($id)];
            if (($this->PDFA || $this->PDFX)) {
                $this->PDFAXwarnings[] = "Cannot use layers when using PDFA or PDFX";
                return '';
            } elseif (!$this->PDFA && !$this->PDFX) {
                $this->pdf_version = '1.5';
            }
        }
        $this->current_layer = $id;
        $this->_out('/OCZ-index /ZI' . $id . ' BDC');

        $this->pageoutput[$this->page] = [];
    }

    public function EndLayer()
    {
        if ($this->current_layer > 0) {
            $this->_out('EMCZ-index');
            $this->current_layer = 0;
        }
    }

    public function AddPageByArray($a)
    {
        if (!is_array($a)) {
            $a = [];
        }

        $orientation = (isset($a['orientation']) ? $a['orientation'] : '');
        $condition = (isset($a['condition']) ? $a['condition'] : (isset($a['type']) ? $a['type'] : ''));
        $resetpagenum = (isset($a['resetpagenum']) ? $a['resetpagenum'] : '');
        $pagenumstyle = (isset($a['pagenumstyle']) ? $a['pagenumstyle'] : '');
        $suppress = (isset($a['suppress']) ? $a['suppress'] : '');
        $mgl = (isset($a['mgl']) ? $a['mgl'] : (isset($a['margin-left']) ? $a['margin-left'] : ''));
        $mgr = (isset($a['mgr']) ? $a['mgr'] : (isset($a['margin-right']) ? $a['margin-right'] : ''));
        $mgt = (isset($a['mgt']) ? $a['mgt'] : (isset($a['margin-top']) ? $a['margin-top'] : ''));
        $mgb = (isset($a['mgb']) ? $a['mgb'] : (isset($a['margin-bottom']) ? $a['margin-bottom'] : ''));
        $mgh = (isset($a['mgh']) ? $a['mgh'] : (isset($a['margin-header']) ? $a['margin-header'] : ''));
        $mgf = (isset($a['mgf']) ? $a['mgf'] : (isset($a['margin-footer']) ? $a['margin-footer'] : ''));
        $ohname = (isset($a['ohname']) ? $a['ohname'] : (isset($a['odd-header-name']) ? $a['odd-header-name'] : ''));
        $ehname = (isset($a['ehname']) ? $a['ehname'] : (isset($a['even-header-name']) ? $a['even-header-name'] : ''));
        $ofname = (isset($a['ofname']) ? $a['ofname'] : (isset($a['odd-footer-name']) ? $a['odd-footer-name'] : ''));
        $efname = (isset($a['efname']) ? $a['efname'] : (isset($a['even-footer-name']) ? $a['even-footer-name'] : ''));
        $ohvalue = (isset($a['ohvalue']) ? $a['ohvalue'] : (isset($a['odd-header-value']) ? $a['odd-header-value'] : 0));
        $ehvalue = (isset($a['ehvalue']) ? $a['ehvalue'] : (isset($a['even-header-value']) ? $a['even-header-value'] : 0));
        $ofvalue = (isset($a['ofvalue']) ? $a['ofvalue'] : (isset($a['odd-footer-value']) ? $a['odd-footer-value'] : 0));
        $efvalue = (isset($a['efvalue']) ? $a['efvalue'] : (isset($a['even-footer-value']) ? $a['even-footer-value'] : 0));
        $pagesel = (isset($a['pagesel']) ? $a['pagesel'] : (isset($a['pageselector']) ? $a['pageselector'] : ''));
        $newformat = (isset($a['newformat']) ? $a['newformat'] : (isset($a['sheet-size']) ? $a['sheet-size'] : ''));

        $this->AddPage($orientation, $condition, $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
    }

    public function AddPage(
        $orientation = '',
        $condition = '',
        $resetpagenum = '',
        $pagenumstyle = '',
        $suppress = '',
        $mgl = '',
        $mgr = '',
        $mgt = '',
        $mgb = '',
        $mgh = '',
        $mgf = '',
        $ohname = '',
        $ehname = '',
        $ofname = '',
        $efname = '',
        $ohvalue = 0,
        $ehvalue = 0,
        $ofvalue = 0,
        $efvalue = 0,
        $pagesel = '',
        $newformat = ''
    ) {
        /* -- CSS-FLOAT -- */
        // Float DIV
        // Cannot do with columns on, or if any change in page orientation/margins etc.
        // If next page already exists - i.e background /headers and footers already written
        if ($this->state > 0 && $this->page < count($this->pages)) {
            $bak_cml = $this->cMarginL;
            $bak_cmr = $this->cMarginR;
            $bak_dw = $this->divwidth;
            // Paint Div Border if necessary
            if ($this->blklvl > 0) {
                $save_tr = $this->table_rotate; // *TABLES*
                $this->table_rotate = 0; // *TABLES*
                if (isset($this->blk[$this->blklvl]['y0']) && $this->y == $this->blk[$this->blklvl]['y0']) {
                    $this->blk[$this->blklvl]['startpage'] ++;
                }
                if ((isset($this->blk[$this->blklvl]['y0']) && $this->y > $this->blk[$this->blklvl]['y0']) || $this->flowingBlockAttr['is_table']) {
                    $toplvl = $this->blklvl;
                } else {
                    $toplvl = $this->blklvl - 1;
                }
                $sy = $this->y;
                for ($bl = 1; $bl <= $toplvl; $bl++) {
                    $this->PaintDivBB('pagebottom', 0, $bl);
                }
                $this->y = $sy;
                $this->table_rotate = $save_tr; // *TABLES*
            }
            $s = $this->PrintPageBackgrounds();

            // Writes after the marker so not overwritten later by page background etc.
            $this->pages[$this->page] = preg_replace(
                '/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/',
                '\\1' . "\n" . $s . "\n",
                $this->pages[$this->page]
            );

            $this->pageBackgrounds = [];
            $family = $this->FontFamily;
            $style = $this->FontStyle;
            $size = $this->FontSizePt;
            $lw = $this->LineWidth;
            $dc = $this->DrawColor;
            $fc = $this->FillColor;
            $tc = $this->TextColor;
            $cf = $this->ColorFlag;

            $this->printfloatbuffer();

            // Move to next page
            $this->page++;

            $this->ResetMargins();
            $this->SetAutoPageBreak($this->autoPageBreak, $this->bMargin);
            $this->x = $this->lMargin;
            $this->y = $this->tMargin;
            $this->FontFamily = '';
            $this->_out('2 J');
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.3F w', $lw * Mpdf::SCALE));

            if ($family) {
                $this->SetFont($family, $style, $size, true, true);
            }

            $this->DrawColor = $dc;

            if ($dc != $this->defDrawColor) {
                $this->_out($dc);
            }

            $this->FillColor = $fc;

            if ($fc != $this->defFillColor) {
                $this->_out($fc);
            }

            $this->TextColor = $tc;
            $this->ColorFlag = $cf;

            for ($bl = 1; $bl <= $this->blklvl; $bl++) {
                $this->blk[$bl]['y0'] = $this->y;
                // Don't correct more than once for background DIV containing a Float
                if (!isset($this->blk[$bl]['marginCorrected'][$this->page])) {
                    if (isset($this->blk[$bl]['x0'])) {
                        $this->blk[$bl]['x0'] += $this->MarginCorrection;
                    } else {
                        $this->blk[$bl]['x0'] = $this->MarginCorrection;
                    }
                }
                $this->blk[$bl]['marginCorrected'][$this->page] = true;
            }

            $this->cMarginL = $bak_cml;
            $this->cMarginR = $bak_cmr;
            $this->divwidth = $bak_dw;

            return '';
        }
        /* -- END CSS-FLOAT -- */

        // Start a new page
        if ($this->state == 0) {
            $this->Open();
        }

        $bak_cml = $this->cMarginL;
        $bak_cmr = $this->cMarginR;
        $bak_dw = $this->divwidth;

        $bak_lh = $this->lineheight;

        $orientation = substr(strtoupper($orientation), 0, 1);
        $condition = strtoupper($condition);


        if ($condition == 'E') { // only adds new page if needed to create an Even page
            if (!$this->mirrorMargins || ($this->page) % 2 == 0) {
                return false;
            }
        } elseif ($condition == 'O') { // only adds new page if needed to create an Odd page
            if (!$this->mirrorMargins || ($this->page) % 2 == 1) {
                return false;
            }
        } elseif ($condition == 'NEXT-EVEN') { // always adds at least one new page to create an Even page
            if (!$this->mirrorMargins) {
                $condition = '';
            } else {
                if ($pagesel) {
                    $pbch = $pagesel;
                    $pagesel = '';
                } // *CSS-PAGE*
                else {
                    $pbch = false;
                } // *CSS-PAGE*
                $this->AddPage($this->CurOrientation, 'O');
                $this->extrapagebreak = true; // mPDF 6 pagebreaktype
                if ($pbch) {
                    $pagesel = $pbch;
                } // *CSS-PAGE*
                $condition = '';
            }
        } elseif ($condition == 'NEXT-ODD') { // always adds at least one new page to create an Odd page
            if (!$this->mirrorMargins) {
                $condition = '';
            } else {
                if ($pagesel) {
                    $pbch = $pagesel;
                    $pagesel = '';
                } // *CSS-PAGE*
                else {
                    $pbch = false;
                } // *CSS-PAGE*
                $this->AddPage($this->CurOrientation, 'E');
                $this->extrapagebreak = true; // mPDF 6 pagebreaktype
                if ($pbch) {
                    $pagesel = $pbch;
                } // *CSS-PAGE*
                $condition = '';
            }
        }

        if ($resetpagenum || $pagenumstyle || $suppress) {
            $this->PageNumSubstitutions[] = ['from' => ($this->page + 1), 'reset' => $resetpagenum, 'type' => $pagenumstyle, 'suppress' => $suppress];
        }

        $save_tr = $this->table_rotate; // *TABLES*
        $this->table_rotate = 0; // *TABLES*
        $save_kwt = $this->kwt;
        $this->kwt = 0;
        $save_layer = $this->current_layer;
        $save_vis = $this->visibility;

        if ($this->visibility != 'visible') {
            $this->SetVisibility('visible');
        }

        $this->EndLayer();

        // Paint Div Border if necessary
        // PAINTS BACKGROUND COLOUR OR BORDERS for DIV - DISABLED FOR COLUMNS (cf. AcceptPageBreak) AT PRESENT in ->PaintDivBB
        if (!$this->ColActive && $this->blklvl > 0) {
            if (isset($this->blk[$this->blklvl]['y0']) && $this->y == $this->blk[$this->blklvl]['y0'] && !$this->extrapagebreak) { // mPDF 6 pagebreaktype
                if (isset($this->blk[$this->blklvl]['startpage'])) {
                    $this->blk[$this->blklvl]['startpage'] ++;
                } else {
                    $this->blk[$this->blklvl]['startpage'] = 1;
                }
            }
            if ((isset($this->blk[$this->blklvl]['y0']) && $this->y > $this->blk[$this->blklvl]['y0']) || $this->flowingBlockAttr['is_table'] || $this->extrapagebreak) {
                $toplvl = $this->blklvl;
            } // mPDF 6 pagebreaktype
            else {
                $toplvl = $this->blklvl - 1;
            }
            $sy = $this->y;
            for ($bl = 1; $bl <= $toplvl; $bl++) {
                if (isset($this->blk[$bl]['z-index']) && $this->blk[$bl]['z-index'] > 0) {
                    $this->BeginLayer($this->blk[$bl]['z-index']);
                }
                if (isset($this->blk[$bl]['visibility']) && $this->blk[$bl]['visibility'] && $this->blk[$bl]['visibility'] != 'visible') {
                    $this->SetVisibility($this->blk[$bl]['visibility']);
                }
                $this->PaintDivBB('pagebottom', 0, $bl);
            }
            $this->y = $sy;
            // RESET block y0 and x0 - see below
        }
        $this->extrapagebreak = false; // mPDF 6 pagebreaktype

        if ($this->visibility != 'visible') {
            $this->SetVisibility('visible');
        }

        $this->EndLayer();

        // BODY Backgrounds
        if ($this->page > 0) {
            $s = '';
            $s .= $this->PrintBodyBackgrounds();

            $s .= $this->PrintPageBackgrounds();
            $this->pages[$this->page] = preg_replace('/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/', "\n" . $s . "\n" . '\\1', $this->pages[$this->page]);
            $this->pageBackgrounds = [];
        }

        $save_kt = $this->keep_block_together;
        $this->keep_block_together = 0;

        $save_cols = false;

        /* -- COLUMNS -- */
        if ($this->ColActive) {
            $save_cols = true;
            $save_nbcol = $this->NbCol; // other values of gap and vAlign will not change by setting Columns off
            $this->SetColumns(0);
        }
        /* -- END COLUMNS -- */

        $family = $this->FontFamily;
        $style = $this->FontStyle;
        $size = $this->FontSizePt;
        $this->ColumnAdjust = true; // enables column height adjustment for the page
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;
        if ($this->page > 0) {
            // Page footer
            $this->InFooter = true;

            $this->Reset();
            $this->pageoutput[$this->page] = [];

            $this->Footer();
            // Close page
            $this->_endpage();
        }

        // Start new page
        $this->_beginpage($orientation, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);

        if ($this->docTemplate) {
            $pagecount = $this->SetSourceFile($this->docTemplate);
            if (($this->page - $this->docTemplateStart) > $pagecount) {
                if ($this->docTemplateContinue) {
                    $tplIdx = $this->ImportPage($pagecount);
                    $this->UseTemplate($tplIdx);
                }
            } else {
                $tplIdx = $this->ImportPage(($this->page - $this->docTemplateStart));
                $this->UseTemplate($tplIdx);
            }
        }

        if ($this->pageTemplate) {
            $this->UseTemplate($this->pageTemplate);
        }

        // Tiling Patterns
        $this->_out('___PAGE___START' . $this->uniqstr);
        $this->_out('___BACKGROUND___PATTERNS' . $this->uniqstr);
        $this->_out('___HEADER___MARKER' . $this->uniqstr);
        $this->pageBackgrounds = [];

        // Set line cap style to square
        $this->SetLineCap(2);
        // Set line width
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.3F w', $lw * Mpdf::SCALE));
        // Set font
        if ($family) {
            $this->SetFont($family, $style, $size, true, true); // forces write
        }

        // Set colors
        $this->DrawColor = $dc;
        if ($dc != $this->defDrawColor) {
            $this->_out($dc);
        }
        $this->FillColor = $fc;
        if ($fc != $this->defFillColor) {
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        // Page header
        $this->Header();

        // Restore line width
        if ($this->LineWidth != $lw) {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.3F w', $lw * Mpdf::SCALE));
        }
        // Restore font
        if ($family) {
            $this->SetFont($family, $style, $size, true, true); // forces write
        }

        // Restore colors
        if ($this->DrawColor != $dc) {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($this->FillColor != $fc) {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        $this->InFooter = false;

        if ($save_layer > 0) {
            $this->BeginLayer($save_layer);
        }

        if ($save_vis != 'visible') {
            $this->SetVisibility($save_vis);
        }

        /* -- COLUMNS -- */
        if ($save_cols) {
            // Restore columns
            $this->SetColumns($save_nbcol, $this->colvAlign, $this->ColGap);
        }
        if ($this->ColActive) {
            $this->SetCol(0);
        }
        /* -- END COLUMNS -- */


        // RESET BLOCK BORDER TOP
        if (!$this->ColActive) {
            for ($bl = 1; $bl <= $this->blklvl; $bl++) {
                $this->blk[$bl]['y0'] = $this->y;
                if (isset($this->blk[$bl]['x0'])) {
                    $this->blk[$bl]['x0'] += $this->MarginCorrection;
                } else {
                    $this->blk[$bl]['x0'] = $this->MarginCorrection;
                }
                // Added mPDF 3.0 Float DIV
                $this->blk[$bl]['marginCorrected'][$this->page] = true;
            }
        }


        $this->table_rotate = $save_tr; // *TABLES*
        $this->kwt = $save_kwt;

        $this->keep_block_together = $save_kt;

        $this->cMarginL = $bak_cml;
        $this->cMarginR = $bak_cmr;
        $this->divwidth = $bak_dw;

        $this->lineheight = $bak_lh;
    }

    /**
     * Get current page number
     *
     * @return int
     */
    public function PageNo()
    {
        return $this->page;
    }

    public function AddSpotColorsFromFile($file)
    {
        $colors = @file($file);
        if (!$colors) {
            throw new \Mpdf\MpdfException("Cannot load spot colors file - " . $file);
        }
        foreach ($colors as $sc) {
            list($name, $c, $m, $y, $k) = preg_split("/\t/", $sc);
            $c = intval($c);
            $m = intval($m);
            $y = intval($y);
            $k = intval($k);
            $this->AddSpotColor($name, $c, $m, $y, $k);
        }
    }

    public function AddSpotColor($name, $c, $m, $y, $k)
    {
        $name = strtoupper(trim($name));
        if (!isset($this->spotColors[$name])) {
            $i = count($this->spotColors) + 1;
            $this->spotColors[$name] = ['i' => $i, 'c' => $c, 'm' => $m, 'y' => $y, 'k' => $k];
            $this->spotColorIDs[$i] = $name;
        }
    }

    public function SetColor($col, $type = '')
    {
        $out = '';
        if (!$col) {
            return '';
        } // mPDF 6
        if ($col{0} == 3 || $col{0} == 5) { // RGB / RGBa
            $out = sprintf('%.3F %.3F %.3F rg', ord($col{1}) / 255, ord($col{2}) / 255, ord($col{3}) / 255);
        } elseif ($col{0} == 1) { // GRAYSCALE
            $out = sprintf('%.3F g', ord($col{1}) / 255);
        } elseif ($col{0} == 2) { // SPOT COLOR
            $out = sprintf('/CS%d cs %.3F scn', ord($col{1}), ord($col{2}) / 100);
        } elseif ($col{0} == 4 || $col{0} == 6) { // CMYK / CMYKa
            $out = sprintf('%.3F %.3F %.3F %.3F k', ord($col{1}) / 100, ord($col{2}) / 100, ord($col{3}) / 100, ord($col{4}) / 100);
        }
        if ($type == 'Draw') {
            $out = strtoupper($out);
        } // e.g. rg => RG
        elseif ($type == 'CodeOnly') {
            $out = preg_replace('/\s(rg|g|k)/', '', $out);
        }
        return $out;
    }

    public function SetDColor($col, $return = false)
    {
        $out = $this->SetColor($col, 'Draw');
        if ($return) {
            return $out;
        }
        if ($out == '') {
            return '';
        }
        $this->DrawColor = $out;
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['DrawColor']) && $this->pageoutput[$this->page]['DrawColor'] != $this->DrawColor) || !isset($this->pageoutput[$this->page]['DrawColor']))) {
            $this->_out($this->DrawColor);
        }
        $this->pageoutput[$this->page]['DrawColor'] = $this->DrawColor;
    }

    public function SetFColor($col, $return = false)
    {
        $out = $this->SetColor($col, 'Fill');
        if ($return) {
            return $out;
        }
        if ($out == '') {
            return '';
        }
        $this->FillColor = $out;
        $this->ColorFlag = ($out != $this->TextColor);
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['FillColor']) && $this->pageoutput[$this->page]['FillColor'] != $this->FillColor) || !isset($this->pageoutput[$this->page]['FillColor']))) {
            $this->_out($this->FillColor);
        }
        $this->pageoutput[$this->page]['FillColor'] = $this->FillColor;
    }

    public function SetTColor($col, $return = false)
    {
        $out = $this->SetColor($col, 'Text');
        if ($return) {
            return $out;
        }
        if ($out == '') {
            return '';
        }
        $this->TextColor = $out;
        $this->ColorFlag = ($this->FillColor != $out);
    }

    public function SetDrawColor($r, $g = -1, $b = -1, $col4 = -1, $return = false)
    {
        // Set color for all stroking operations
        $col = [];
        if (($r == 0 and $g == 0 and $b == 0 && $col4 == -1) or $g == -1) {
            $col = $this->colorConverter->convert($r, $this->PDFAXwarnings);
        } elseif ($col4 == -1) {
            $col = $this->colorConverter->convert('rgb(' . $r . ',' . $g . ',' . $b . ')', $this->PDFAXwarnings);
        } else {
            $col = $this->colorConverter->convert('cmyk(' . $r . ',' . $g . ',' . $b . ',' . $col4 . ')', $this->PDFAXwarnings);
        }
        $out = $this->SetDColor($col, $return);
        return $out;
    }

    public function SetFillColor($r, $g = -1, $b = -1, $col4 = -1, $return = false)
    {
        // Set color for all filling operations
        $col = [];
        if (($r == 0 and $g == 0 and $b == 0 && $col4 == -1) or $g == -1) {
            $col = $this->colorConverter->convert($r, $this->PDFAXwarnings);
        } elseif ($col4 == -1) {
            $col = $this->colorConverter->convert('rgb(' . $r . ',' . $g . ',' . $b . ')', $this->PDFAXwarnings);
        } else {
            $col = $this->colorConverter->convert('cmyk(' . $r . ',' . $g . ',' . $b . ',' . $col4 . ')', $this->PDFAXwarnings);
        }
        $out = $this->SetFColor($col, $return);
        return $out;
    }

    public function SetTextColor($r, $g = -1, $b = -1, $col4 = -1, $return = false)
    {
        // Set color for text
        $col = [];
        if (($r == 0 and $g == 0 and $b == 0 && $col4 == -1) or $g == -1) {
            $col = $this->colorConverter->convert($r, $this->PDFAXwarnings);
        } elseif ($col4 == -1) {
            $col = $this->colorConverter->convert('rgb(' . $r . ',' . $g . ',' . $b . ')', $this->PDFAXwarnings);
        } else {
            $col = $this->colorConverter->convert('cmyk(' . $r . ',' . $g . ',' . $b . ',' . $col4 . ')', $this->PDFAXwarnings);
        }
        $out = $this->SetTColor($col, $return);
        return $out;
    }

    public function GetCharWidthCore($c)
    {
        // Get width of a single character in the current Core font
        $c = (string) $c;
        $w = 0;
        // Soft Hyphens chr(173)
        if ($c == chr(173) && $this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats') {
            return 0;
        } elseif (($this->textvar & TextVars::FC_SMALLCAPS) && isset($this->upperCase[ord($c)])) {  // mPDF 5.7.1
            $charw = $this->CurrentFont['cw'][chr($this->upperCase[ord($c)])];
            if ($charw !== false) {
                $charw = $charw * $this->smCapsScale * $this->smCapsStretch / 100;
                $w+=$charw;
            }
        } elseif (isset($this->CurrentFont['cw'][$c])) {
            $w += $this->CurrentFont['cw'][$c];
        } elseif (isset($this->CurrentFont['cw'][ord($c)])) {
            $w += $this->CurrentFont['cw'][ord($c)];
        }
        $w *= ($this->FontSize / 1000);
        if ($this->minwSpacing || $this->fixedlSpacing) {
            if ($c == ' ') {
                $nb_spaces = 1;
            } else {
                $nb_spaces = 0;
            }
            $w += $this->fixedlSpacing + ($nb_spaces * $this->minwSpacing);
        }
        return ($w);
    }

    public function GetCharWidthNonCore($c, $addSubset = true)
    {
        // Get width of a single character in the current Non-Core font
        $c = (string) $c;
        $w = 0;
        $unicode = $this->UTF8StringToArray($c, $addSubset);
        $char = $unicode[0];
        /* -- CJK-FONTS -- */
        if ($this->CurrentFont['type'] == 'Type0') { // CJK Adobe fonts
            if ($char == 173) {
                return 0;
            } // Soft Hyphens
            elseif (isset($this->CurrentFont['cw'][$char])) {
                $w+=$this->CurrentFont['cw'][$char];
            } elseif (isset($this->CurrentFont['MissingWidth'])) {
                $w += $this->CurrentFont['MissingWidth'];
            } else {
                $w += 500;
            }
        } else {
            /* -- END CJK-FONTS -- */
            if ($char == 173) {
                return 0;
            } // Soft Hyphens
            elseif (($this->textvar & TextVars::FC_SMALLCAPS) && isset($this->upperCase[$char])) { // mPDF 5.7.1
                $charw = $this->_getCharWidth($this->CurrentFont['cw'], $this->upperCase[$char]);
                if ($charw !== false) {
                    $charw = $charw * $this->smCapsScale * $this->smCapsStretch / 100;
                    $w+=$charw;
                } elseif (isset($this->CurrentFont['desc']['MissingWidth'])) {
                    $w += $this->CurrentFont['desc']['MissingWidth'];
                } elseif (isset($this->CurrentFont['MissingWidth'])) {
                    $w += $this->CurrentFont['MissingWidth'];
                } else {
                    $w += 500;
                }
            } else {
                $charw = $this->_getCharWidth($this->CurrentFont['cw'], $char);
                if ($charw !== false) {
                    $w+=$charw;
                } elseif (isset($this->CurrentFont['desc']['MissingWidth'])) {
                    $w += $this->CurrentFont['desc']['MissingWidth'];
                } elseif (isset($this->CurrentFont['MissingWidth'])) {
                    $w += $this->CurrentFont['MissingWidth'];
                } else {
                    $w += 500;
                }
            }
        } // *CJK-FONTS*
        $w *= ($this->FontSize / 1000);
        if ($this->minwSpacing || $this->fixedlSpacing) {
            if ($c == ' ') {
                $nb_spaces = 1;
            } else {
                $nb_spaces = 0;
            }
            $w += $this->fixedlSpacing + ($nb_spaces * $this->minwSpacing);
        }
        return ($w);
    }

    public function GetCharWidth($c, $addSubset = true)
    {
        if (!$this->usingCoreFont) {
            return $this->GetCharWidthNonCore($c, $addSubset);
        } else {
            return $this->GetCharWidthCore($c);
        }
    }

    public function GetStringWidth($s, $addSubset = true, $OTLdata = false, $textvar = 0, $includeKashida = false)
    {
    // mPDF 5.7.1
        // Get width of a string in the current font
        $s = (string) $s;
        $cw = &$this->CurrentFont['cw'];
        $w = 0;
        $kerning = 0;
        $lastchar = 0;
        $nb_carac = 0;
        $nb_spaces = 0;
        $kashida = 0;
        // mPDF ITERATION
        if ($this->iterationCounter) {
            $s = preg_replace('/{iteration ([a-zA-Z0-9_]+)}/', '\\1', $s);
        }
        if (!$this->usingCoreFont) {
            $discards = substr_count($s, "\xc2\xad"); // mPDF 6 soft hyphens [U+00AD]
            $unicode = $this->UTF8StringToArray($s, $addSubset);
            if ($this->minwSpacing || $this->fixedlSpacing) {
                $nb_spaces = mb_substr_count($s, ' ', $this->mb_enc);
                $nb_carac = count($unicode) - $discards; // mPDF 6
                // mPDF 5.7.1
                // Use GPOS OTL
                if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                    if (isset($OTLdata['group']) && $OTLdata['group']) {
                        $nb_carac -= substr_count($OTLdata['group'], 'M');
                    }
                }
            }
            /* -- CJK-FONTS -- */
            if ($this->CurrentFont['type'] == 'Type0') { // CJK Adobe fonts
                foreach ($unicode as $char) {
                    if ($char == 0x00AD) {
                        continue;
                    } // mPDF 6 soft hyphens [U+00AD]
                    if (isset($cw[$char])) {
                        $w+=$cw[$char];
                    } elseif (isset($this->CurrentFont['MissingWidth'])) {
                        $w += $this->CurrentFont['MissingWidth'];
                    } else {
                        $w += 500;
                    }
                }
            } else {
                /* -- END CJK-FONTS -- */
                foreach ($unicode as $i => $char) {
                    if ($char == 0x00AD) {
                        continue;
                    } // mPDF 6 soft hyphens [U+00AD]
                    if (($textvar & TextVars::FC_SMALLCAPS) && isset($this->upperCase[$char])) {
                        $charw = $this->_getCharWidth($cw, $this->upperCase[$char]);
                        if ($charw !== false) {
                            $charw = $charw * $this->smCapsScale * $this->smCapsStretch / 100;
                            $w+=$charw;
                        } elseif (isset($this->CurrentFont['desc']['MissingWidth'])) {
                            $w += $this->CurrentFont['desc']['MissingWidth'];
                        } elseif (isset($this->CurrentFont['MissingWidth'])) {
                            $w += $this->CurrentFont['MissingWidth'];
                        } else {
                            $w += 500;
                        }
                    } else {
                        $charw = $this->_getCharWidth($cw, $char);
                        if ($charw !== false) {
                            $w+=$charw;
                        } elseif (isset($this->CurrentFont['desc']['MissingWidth'])) {
                            $w += $this->CurrentFont['desc']['MissingWidth'];
                        } elseif (isset($this->CurrentFont['MissingWidth'])) {
                            $w += $this->CurrentFont['MissingWidth'];
                        } else {
                            $w += 500;
                        }
                        // mPDF 5.7.1
                        // Use GPOS OTL
                        // ...GetStringWidth...
                        if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF) && !empty($OTLdata)) {
                            if (isset($OTLdata['GPOSinfo'][$i]['wDir']) && $OTLdata['GPOSinfo'][$i]['wDir'] == 'RTL') {
                                if (isset($OTLdata['GPOSinfo'][$i]['XAdvanceR']) && $OTLdata['GPOSinfo'][$i]['XAdvanceR']) {
                                    $w += $OTLdata['GPOSinfo'][$i]['XAdvanceR'] * 1000 / $this->CurrentFont['unitsPerEm'];
                                }
                            } else {
                                if (isset($OTLdata['GPOSinfo'][$i]['XAdvanceL']) && $OTLdata['GPOSinfo'][$i]['XAdvanceL']) {
                                    $w += $OTLdata['GPOSinfo'][$i]['XAdvanceL'] * 1000 / $this->CurrentFont['unitsPerEm'];
                                }
                            }
                            // Kashida from GPOS
                            // Kashida is set as an absolute length value (already set as a proportion based on useKashida %)
                            if ($includeKashida && isset($OTLdata['GPOSinfo'][$i]['kashida_space']) && $OTLdata['GPOSinfo'][$i]['kashida_space']) {
                                $kashida += $OTLdata['GPOSinfo'][$i]['kashida_space'];
                            }
                        }
                        if (($textvar & TextVars::FC_KERNING) && $lastchar) {
                            if (isset($this->CurrentFont['kerninfo'][$lastchar][$char])) {
                                $kerning += $this->CurrentFont['kerninfo'][$lastchar][$char];
                            }
                        }
                        $lastchar = $char;
                    }
                }
            } // *CJK-FONTS*
        } else {
            if ($this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats') {
                $s = str_replace(chr(173), '', $s);
            }
            $nb_carac = $l = strlen($s);
            if ($this->minwSpacing || $this->fixedlSpacing) {
                $nb_spaces = substr_count($s, ' ');
            }
            for ($i = 0; $i < $l; $i++) {
                if (($textvar & TextVars::FC_SMALLCAPS) && isset($this->upperCase[ord($s[$i])])) {  // mPDF 5.7.1
                    $charw = $cw[chr($this->upperCase[ord($s[$i])])];
                    if ($charw !== false) {
                        $charw = $charw * $this->smCapsScale * $this->smCapsStretch / 100;
                        $w+=$charw;
                    }
                } elseif (isset($cw[$s[$i]])) {
                    $w += $cw[$s[$i]];
                } elseif (isset($cw[ord($s[$i])])) {
                    $w += $cw[ord($s[$i])];
                }
                if (($textvar & TextVars::FC_KERNING) && $i > 0) { // mPDF 5.7.1
                    if (isset($this->CurrentFont['kerninfo'][$s[($i - 1)]][$s[$i]])) {
                        $kerning += $this->CurrentFont['kerninfo'][$s[($i - 1)]][$s[$i]];
                    }
                }
            }
        }
        unset($cw);
        if ($textvar & TextVars::FC_KERNING) {
            $w += $kerning;
        } // mPDF 5.7.1
        $w *= ($this->FontSize / 1000);
        $w += (($nb_carac + $nb_spaces) * $this->fixedlSpacing) + ($nb_spaces * $this->minwSpacing);
        $w += $kashida / Mpdf::SCALE;

        return ($w);
    }

    public function SetLineWidth($width)
    {
        // Set line width
        $this->LineWidth = $width;
        $lwout = (sprintf('%.3F w', $width * Mpdf::SCALE));
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['LineWidth']) && $this->pageoutput[$this->page]['LineWidth'] != $lwout) || !isset($this->pageoutput[$this->page]['LineWidth']))) {
            $this->_out($lwout);
        }
        $this->pageoutput[$this->page]['LineWidth'] = $lwout;
    }

    public function Line($x1, $y1, $x2, $y2)
    {
        // Draw a line
        $this->_out(sprintf('%.3F %.3F m %.3F %.3F l S', $x1 * Mpdf::SCALE, ($this->h - $y1) * Mpdf::SCALE, $x2 * Mpdf::SCALE, ($this->h - $y2) * Mpdf::SCALE));
    }

    public function Arrow($x1, $y1, $x2, $y2, $headsize = 3, $fill = 'B', $angle = 25)
    {
        // F == fill // S == stroke // B == stroke and fill
        // angle = splay of arrowhead - 1 - 89 degrees
        if ($fill == 'F') {
            $fill = 'f';
        } elseif ($fill == 'FD' or $fill == 'DF' or $fill == 'B') {
            $fill = 'B';
        } else {
            $fill = 'S';
        }
        $a = atan2(($y2 - $y1), ($x2 - $x1));
        $b = $a + deg2rad($angle);
        $c = $a - deg2rad($angle);
        $x3 = $x2 - ($headsize * cos($b));
        $y3 = $this->h - ($y2 - ($headsize * sin($b)));
        $x4 = $x2 - ($headsize * cos($c));
        $y4 = $this->h - ($y2 - ($headsize * sin($c)));

        $x5 = $x3 - ($x3 - $x4) / 2; // mid point of base of arrowhead - to join arrow line to
        $y5 = $y3 - ($y3 - $y4) / 2;

        $s = '';
        $s.=sprintf('%.3F %.3F m %.3F %.3F l S', $x1 * Mpdf::SCALE, ($this->h - $y1) * Mpdf::SCALE, $x5 * Mpdf::SCALE, $y5 * Mpdf::SCALE);
        $this->_out($s);

        $s = '';
        $s.=sprintf('%.3F %.3F m %.3F %.3F l %.3F %.3F l %.3F %.3F l %.3F %.3F l ', $x5 * Mpdf::SCALE, $y5 * Mpdf::SCALE, $x3 * Mpdf::SCALE, $y3 * Mpdf::SCALE, $x2 * Mpdf::SCALE, ($this->h - $y2) * Mpdf::SCALE, $x4 * Mpdf::SCALE, $y4 * Mpdf::SCALE, $x5 * Mpdf::SCALE, $y5 * Mpdf::SCALE);
        $s.=$fill;
        $this->_out($s);
    }

    public function Rect($x, $y, $w, $h, $style = '')
    {
        // Draw a rectangle
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' or $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $this->_out(sprintf('%.3F %.3F %.3F %.3F re %s', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$h * Mpdf::SCALE, $op));
    }

    public function AddFontDirectory($directory)
    {
        $this->fontDir[] = $directory;
        $this->fontFileFinder->setDirectories($this->fontDir);
    }

    public function AddFont($family, $style = '')
    {
        if (empty($family)) {
            return;
        }

        $family = strtolower($family);
        $style = strtoupper($style);
        $style = str_replace('U', '', $style);

        if ($style == 'IB') {
            $style = 'BI';
        }

        $fontkey = $family . $style;

        // check if the font has been already added
        if (isset($this->fonts[$fontkey])) {
            return;
        }

        /* -- CJK-FONTS -- */
        if (in_array($family, $this->available_CJK_fonts)) {
            if (empty($this->Big5_widths)) {
                require __DIR__ . '/../data/CJKdata.php';
            }
            $this->AddCJKFont($family); // don't need to add style
            return;
        }
        /* -- END CJK-FONTS -- */

        if ($this->usingCoreFont) {
            throw new \Mpdf\MpdfException("mPDF Error - problem with Font management");
        }

        $stylekey = $style;
        if (!$style) {
            $stylekey = 'R';
        }

        if (!isset($this->fontdata[$family][$stylekey]) || !$this->fontdata[$family][$stylekey]) {
            throw new \Mpdf\MpdfException(sprintf('Font "%s%s%s" is not supported', $family, $style ? ' - ' : '', $style));
        }

        $name = '';
        $cw = '';
        $glyphIDtoUni = '';
        $originalsize = 0;
        $sip = false;
        $smp = false;
        $useOTL = 0; // mPDF 5.7.1
        $fontmetrics = ''; // mPDF 6
        $haskerninfo = false;
        $haskernGPOS = false;
        $hassmallcapsGSUB = false;
        $BMPselected = false;
        $GSUBScriptLang = [];
        $GSUBFeatures = [];
        $GSUBLookups = [];
        $GPOSScriptLang = [];
        $GPOSFeatures = [];
        $GPOSLookups = [];

        if ($this->fontCache->has($fontkey . '.mtx.php')) {
            require $this->fontCache->tempFilename($fontkey . '.mtx.php');
        }

        $ttffile = $this->fontFileFinder->findFontFile($this->fontdata[$family][$stylekey]);
        $ttfstat = stat($ttffile);

        if (isset($this->fontdata[$family]['TTCfontID'][$stylekey])) {
            $TTCfontID = $this->fontdata[$family]['TTCfontID'][$stylekey];
        } else {
            $TTCfontID = 0;
        }

        $fontUseOTL = isset($this->fontdata[$family]['useOTL']) ? $this->fontdata[$family]['useOTL'] : false;

        $BMPonly = false;
        if (in_array($family, $this->BMPonly)) {
            $BMPonly = true;
        }

        $regenerate = false;
        if ($BMPonly && !$BMPselected) {
            $regenerate = true;
        } elseif (!$BMPonly && $BMPselected) {
            $regenerate = true;
        }

        // mPDF 5.7.1
        if ($fontUseOTL && $useOTL != $fontUseOTL) {
            $regenerate = true;
            $useOTL = $fontUseOTL;
        } elseif (!$fontUseOTL && $useOTL) {
            $regenerate = true;
            $useOTL = 0;
        }

        if ($this->fontDescriptor != $fontmetrics) {
            $regenerate = true;
        } // mPDF 6

        if (empty($name) || $originalsize != $ttfstat['size'] || $regenerate) {
            $generator = new MetricsGenerator($this->fontCache, $this->fontDescriptor);

            $generator->generateMetrics(
                $ttffile,
                $ttfstat,
                $fontkey,
                $TTCfontID,
                $this->debugfonts,
                $BMPonly,
                $useOTL,
                $fontUseOTL
            );

            require $this->fontCache->tempFilename($fontkey . '.mtx.php');
            $cw = $this->fontCache->load($fontkey . '.cw.dat');
            $glyphIDtoUni = $this->fontCache->load($fontkey . '.gid.dat');
        } else {
            if ($this->fontCache->has($fontkey . '.cw.dat')) {
                $cw = $this->fontCache->load($fontkey . '.cw.dat');
            }

            if ($this->fontCache->has($fontkey . '.gid.dat')) {
                $glyphIDtoUni = $this->fontCache->load($fontkey . '.gid.dat');
            }
        }

        if (isset($this->fontdata[$family]['sip-ext']) && $this->fontdata[$family]['sip-ext']) {
            $sipext = $this->fontdata[$family]['sip-ext'];
        } else {
            $sipext = '';
        }

        // Override with values from config_font.php
        if (isset($this->fontdata[$family]['Ascent']) && $this->fontdata[$family]['Ascent']) {
            $desc['Ascent'] = $this->fontdata[$family]['Ascent'];
        }
        if (isset($this->fontdata[$family]['Descent']) && $this->fontdata[$family]['Descent']) {
            $desc['Descent'] = $this->fontdata[$family]['Descent'];
        }
        if (isset($this->fontdata[$family]['Leading']) && $this->fontdata[$family]['Leading']) {
            $desc['Leading'] = $this->fontdata[$family]['Leading'];
        }

        $i = count($this->fonts) + $this->extraFontSubsets + 1;
        if ($sip || $smp) {
            $this->fonts[$fontkey] = [
                'i' => $i,
                'type' => $type,
                'name' => $name,
                'desc' => $desc,
                'panose' => $panose,
                'unitsPerEm' => $unitsPerEm,
                'up' => $up,
                'ut' => $ut,
                'strs' => $strs,
                'strp' => $strp,
                'cw' => $cw,
                'ttffile' => $ttffile,
                'fontkey' => $fontkey,
                'subsets' => [0 => range(0, 127)],
                'subsetfontids' => [$i],
                'used' => false,
                'sip' => $sip,
                'sipext' => $sipext,
                'smp' => $smp,
                'TTCfontID' => $TTCfontID,
                'useOTL' => $fontUseOTL,
                'useKashida' => (isset($this->fontdata[$family]['useKashida']) ? $this->fontdata[$family]['useKashida'] : false),
                'GSUBScriptLang' => $GSUBScriptLang,
                'GSUBFeatures' => $GSUBFeatures,
                'GSUBLookups' => $GSUBLookups,
                'GPOSScriptLang' => $GPOSScriptLang,
                'GPOSFeatures' => $GPOSFeatures,
                'GPOSLookups' => $GPOSLookups,
                'rtlPUAstr' => $rtlPUAstr,
                'glyphIDtoUni' => $glyphIDtoUni,
                'haskerninfo' => $haskerninfo,
                'haskernGPOS' => $haskernGPOS,
                'hassmallcapsGSUB' => $hassmallcapsGSUB]; // mPDF 5.7.1 // mPDF 6
        } else {
            $ss = [];
            for ($s = 32; $s < 128; $s++) {
                $ss[$s] = $s;
            }
            $this->fonts[$fontkey] = [
                'i' => $i,
                'type' => $type,
                'name' => $name,
                'desc' => $desc,
                'panose' => $panose,
                'unitsPerEm' => $unitsPerEm,
                'up' => $up,
                'ut' => $ut,
                'strs' => $strs,
                'strp' => $strp,
                'cw' => $cw,
                'ttffile' => $ttffile,
                'fontkey' => $fontkey,
                'subset' => $ss,
                'used' => false,
                'sip' => $sip,
                'sipext' => $sipext,
                'smp' => $smp,
                'TTCfontID' => $TTCfontID,
                'useOTL' => $fontUseOTL,
                'useKashida' => (isset($this->fontdata[$family]['useKashida']) ? $this->fontdata[$family]['useKashida'] : false),
                'GSUBScriptLang' => $GSUBScriptLang,
                'GSUBFeatures' => $GSUBFeatures,
                'GSUBLookups' => $GSUBLookups,
                'GPOSScriptLang' => $GPOSScriptLang,
                'GPOSFeatures' => $GPOSFeatures,
                'GPOSLookups' => $GPOSLookups,
                'rtlPUAstr' => $rtlPUAstr,
                'glyphIDtoUni' => $glyphIDtoUni,
                'haskerninfo' => $haskerninfo,
                'haskernGPOS' => $haskernGPOS,
                'hassmallcapsGSUB' => $hassmallcapsGSUB
            ];
        }

        if ($haskerninfo) {
            $this->fonts[$fontkey]['kerninfo'] = $kerninfo;
        }

        $this->FontFiles[$fontkey] = [
            'length1' => $originalsize,
            'type' => 'TTF',
            'ttffile' => $ttffile,
            'sip' => $sip,
            'smp' => $smp
        ];

        unset($cw);
    }

    public function SetFont($family, $style = '', $size = 0, $write = true, $forcewrite = false)
    {
        $family = strtolower($family);

        if (!$this->onlyCoreFonts) {
            if ($family == 'sans' || $family == 'sans-serif') {
                $family = $this->sans_fonts[0];
            }
            if ($family == 'serif') {
                $family = $this->serif_fonts[0];
            }
            if ($family == 'mono' || $family == 'monospace') {
                $family = $this->mono_fonts[0];
            }
        }

        if (isset($this->fonttrans[$family]) && $this->fonttrans[$family]) {
            $family = $this->fonttrans[$family];
        }

        if ($family == '') {
            if ($this->FontFamily) {
                $family = $this->FontFamily;
            } elseif ($this->default_font) {
                $family = $this->default_font;
            } else {
                throw new \Mpdf\MpdfException("No font or default font set!");
            }
        }

        $this->ReqFontStyle = $style; // required or requested style - used later for artificial bold/italic

        if (($family == 'csymbol') || ($family == 'czapfdingbats') || ($family == 'ctimes') || ($family == 'ccourier') || ($family == 'chelvetica')) {
            if ($this->PDFA || $this->PDFX) {
                if ($family == 'csymbol' || $family == 'czapfdingbats') {
                    throw new \Mpdf\MpdfException("Symbol and Zapfdingbats cannot be embedded in mPDF (required for PDFA1-b or PDFX/1-a).");
                }
                if ($family == 'ctimes' || $family == 'ccourier' || $family == 'chelvetica') {
                    if (($this->PDFA && !$this->PDFAauto) || ($this->PDFX && !$this->PDFXauto)) {
                        $this->PDFAXwarnings[] = "Core Adobe font " . ucfirst($family) . " cannot be embedded in mPDF, which is required for PDFA1-b or PDFX/1-a. (Embedded font will be substituted.)";
                    }
                    if ($family == 'chelvetica') {
                        $family = 'sans';
                    }
                    if ($family == 'ctimes') {
                        $family = 'serif';
                    }
                    if ($family == 'ccourier') {
                        $family = 'mono';
                    }
                }
                $this->usingCoreFont = false;
            } else {
                $this->usingCoreFont = true;
            }
            if ($family == 'csymbol' || $family == 'czapfdingbats') {
                $style = '';
            }
        } else {
            $this->usingCoreFont = false;
        }

        // mPDF 5.7.1
        if ($style) {
            $style = strtoupper($style);
            if ($style == 'IB') {
                $style = 'BI';
            }
        }
        if ($size == 0) {
            $size = $this->FontSizePt;
        }

        $fontkey = $family . $style;

        $stylekey = $style;
        if (!$stylekey) {
            $stylekey = "R";
        }

        if (!$this->onlyCoreFonts && !$this->usingCoreFont) {
            if (!isset($this->fonts[$fontkey]) || count($this->default_available_fonts) != count($this->available_unifonts)) { // not already added

                /* -- CJK-FONTS -- */
                if (in_array($fontkey, $this->available_CJK_fonts)) {
                    if (!isset($this->fonts[$fontkey])) { // already added
                        if (empty($this->Big5_widths)) {
                            require __DIR__ . '/../data/CJKdata.php';
                        }
                        $this->AddCJKFont($family); // don't need to add style
                    }
                } else { // Test to see if requested font/style is available - or substitute /* -- END CJK-FONTS -- */
                    if (!in_array($fontkey, $this->available_unifonts)) {
                        // If font[nostyle] exists - set it
                        if (in_array($family, $this->available_unifonts)) {
                            $style = '';
                        } // elseif only one font available - set it (assumes if only one font available it will not have a style)
                        elseif (count($this->available_unifonts) == 1) {
                            $family = $this->available_unifonts[0];
                            $style = '';
                        } else {
                            $found = 0;
                            // else substitute font of similar type
                            if (in_array($family, $this->sans_fonts)) {
                                $i = array_intersect($this->sans_fonts, $this->available_unifonts);
                                if (count($i)) {
                                    $i = array_values($i);
                                    // with requested style if possible
                                    if (!in_array(($i[0] . $style), $this->available_unifonts)) {
                                        $style = '';
                                    }
                                    $family = $i[0];
                                    $found = 1;
                                }
                            } elseif (in_array($family, $this->serif_fonts)) {
                                $i = array_intersect($this->serif_fonts, $this->available_unifonts);
                                if (count($i)) {
                                    $i = array_values($i);
                                    // with requested style if possible
                                    if (!in_array(($i[0] . $style), $this->available_unifonts)) {
                                        $style = '';
                                    }
                                    $family = $i[0];
                                    $found = 1;
                                }
                            } elseif (in_array($family, $this->mono_fonts)) {
                                $i = array_intersect($this->mono_fonts, $this->available_unifonts);
                                if (count($i)) {
                                    $i = array_values($i);
                                    // with requested style if possible
                                    if (!in_array(($i[0] . $style), $this->available_unifonts)) {
                                        $style = '';
                                    }
                                    $family = $i[0];
                                    $found = 1;
                                }
                            }

                            if (!$found) {
                                // set first available font
                                $fs = $this->available_unifonts[0];
                                preg_match('/^([a-z_0-9\-]+)([BI]{0,2})$/', $fs, $fas); // Allow "-"
                                // with requested style if possible
                                $ws = $fas[1] . $style;
                                if (in_array($ws, $this->available_unifonts)) {
                                    $family = $fas[1]; // leave $style as is
                                } elseif (in_array($fas[1], $this->available_unifonts)) {
                                    // or without style
                                    $family = $fas[1];
                                    $style = '';
                                } else {
                                    // or with the style specified
                                    $family = $fas[1];
                                    $style = $fas[2];
                                }
                            }
                        }
                        $fontkey = $family . $style;
                    }
                }
            }

            // try to add font (if not already added)
            $this->AddFont($family, $style);

            // Test if font is already selected
            if ($this->FontFamily == $family && $this->FontFamily == $this->currentfontfamily && $this->FontStyle == $style && $this->FontStyle == $this->currentfontstyle && $this->FontSizePt == $size && $this->FontSizePt == $this->currentfontsize && !$forcewrite) {
                return $family;
            }

            $fontkey = $family . $style;

            // Select it
            $this->FontFamily = $family;
            $this->FontStyle = $style;
            $this->FontSizePt = $size;
            $this->FontSize = $size / Mpdf::SCALE;
            $this->CurrentFont = &$this->fonts[$fontkey];
            if ($write) {
                $fontout = (sprintf('BT /F%d %.3F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
                if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['Font']) && $this->pageoutput[$this->page]['Font'] != $fontout) || !isset($this->pageoutput[$this->page]['Font']))) {
                    $this->_out($fontout);
                }
                $this->pageoutput[$this->page]['Font'] = $fontout;
            }

            // Added - currentfont (lowercase) used in HTML2PDF
            $this->currentfontfamily = $family;
            $this->currentfontsize = $size;
            $this->currentfontstyle = $style;
            $this->setMBencoding('UTF-8');
        } else {  // if using core fonts
            if ($this->PDFA || $this->PDFX) {
                throw new \Mpdf\MpdfException('Core Adobe fonts cannot be embedded in mPDF (required for PDFA1-b or PDFX/1-a) - cannot use option to use core fonts.');
            }
            $this->setMBencoding('windows-1252');

            // Test if font is already selected
            if (($this->FontFamily == $family) and ( $this->FontStyle == $style) and ( $this->FontSizePt == $size) && !$forcewrite) {
                return $family;
            }

            if (!isset($this->CoreFonts[$fontkey])) {
                if (in_array($family, $this->serif_fonts)) {
                    $family = 'ctimes';
                } elseif (in_array($family, $this->mono_fonts)) {
                    $family = 'ccourier';
                } else {
                    $family = 'chelvetica';
                }
                $this->usingCoreFont = true;
                $fontkey = $family . $style;
            }

            if (!isset($this->fonts[$fontkey])) {
                // STANDARD CORE FONTS
                if (isset($this->CoreFonts[$fontkey])) {
                    // Load metric file
                    $file = $family;
                    if ($family == 'ctimes' || $family == 'chelvetica' || $family == 'ccourier') {
                        $file .= strtolower($style);
                    }
                    require __DIR__ . '/../data/font/' . $file . '.php';
                    if (!isset($cw)) {
                        throw new \Mpdf\MpdfException(sprintf('Could not include font metric file "%s"', $file));
                    }
                    $i = count($this->fonts) + $this->extraFontSubsets + 1;
                    $this->fonts[$fontkey] = ['i' => $i, 'type' => 'core', 'name' => $this->CoreFonts[$fontkey], 'desc' => $desc, 'up' => $up, 'ut' => $ut, 'cw' => $cw];
                    if ($this->useKerning && isset($kerninfo)) {
                        $this->fonts[$fontkey]['kerninfo'] = $kerninfo;
                    }
                } else {
                    throw new \Mpdf\MpdfException(sprintf('Font %s not defined', $fontkey));
                }
            }

            // Test if font is already selected
            if (($this->FontFamily == $family) and ( $this->FontStyle == $style) and ( $this->FontSizePt == $size) && !$forcewrite) {
                return $family;
            }
            // Select it
            $this->FontFamily = $family;
            $this->FontStyle = $style;
            $this->FontSizePt = $size;
            $this->FontSize = $size / Mpdf::SCALE;
            $this->CurrentFont = &$this->fonts[$fontkey];
            if ($write) {
                $fontout = (sprintf('BT /F%d %.3F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
                if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['Font']) && $this->pageoutput[$this->page]['Font'] != $fontout) || !isset($this->pageoutput[$this->page]['Font']))) {
                    $this->_out($fontout);
                }
                $this->pageoutput[$this->page]['Font'] = $fontout;
            }
            // Added - currentfont (lowercase) used in HTML2PDF
            $this->currentfontfamily = $family;
            $this->currentfontsize = $size;
            $this->currentfontstyle = $style;
        }

        return $family;
    }

    public function SetFontSize($size, $write = true)
    {
        // Set font size in points
        if ($this->FontSizePt == $size) {
            return;
        }
        $this->FontSizePt = $size;
        $this->FontSize = $size / Mpdf::SCALE;
        $this->currentfontsize = $size;
        if ($write) {
            $fontout = (sprintf('BT /F%d %.3F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
            // Edited mPDF 3.0
            if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['Font']) && $this->pageoutput[$this->page]['Font'] != $fontout) || !isset($this->pageoutput[$this->page]['Font']))) {
                $this->_out($fontout);
            }
            $this->pageoutput[$this->page]['Font'] = $fontout;
        }
    }

    public function AddLink()
    {
        // Create a new internal link
        $n = count($this->links) + 1;
        $this->links[$n] = [0, 0];
        return $n;
    }

    public function SetLink($link, $y = 0, $page = -1)
    {
        // Set destination of internal link
        if ($y == -1) {
            $y = $this->y;
        }
        if ($page == -1) {
            $page = $this->page;
        }
        $this->links[$link] = [$page, $y];
    }

    public function Link($x, $y, $w, $h, $link)
    {
        $l = [$x * Mpdf::SCALE, $this->hPt - $y * Mpdf::SCALE, $w * Mpdf::SCALE, $h * Mpdf::SCALE, $link];
        if ($this->keep_block_together) { // don't write yet
            return;
        } elseif ($this->table_rotate) { // *TABLES*
            $this->tbrot_Links[$this->page][] = $l; // *TABLES*
            return; // *TABLES*
        } // *TABLES*
        elseif ($this->kwt) {
            $this->kwt_Links[$this->page][] = $l;
            return;
        }

        if ($this->writingHTMLheader || $this->writingHTMLfooter) {
            $this->HTMLheaderPageLinks[] = $l;
            return;
        }
        // Put a link on the page
        $this->PageLinks[$this->page][] = $l;
        // Save cross-reference to Column buffer
        $ref = count($this->PageLinks[$this->page]) - 1; // *COLUMNS*
        $this->columnLinks[$this->CurrCol][(int) $this->x][(int) $this->y] = $ref; // *COLUMNS*
    }

    public function Text($x, $y, $txt, $OTLdata = [], $textvar = 0, $aixextra = '', $coordsys = '', $return = false)
    {
        // Output (or return) a string
        // Called (internally) by Watermark() & _tableWrite() [rotated cells] & TableHeaderFooter() & WriteText()
        // Called also from classes/svg.php
        // Expects Font to be set
        // Expects input to be mb_encoded if necessary and RTL reversed & OTL processed
        // ARTIFICIAL BOLD AND ITALIC
        $s = 'q ';
        if ($this->falseBoldWeight && strpos($this->ReqFontStyle, "B") !== false && strpos($this->FontStyle, "B") === false) {
            $s .= '2 Tr 1 J 1 j ';
            $s .= sprintf('%.3F w ', ($this->FontSize / 130) * Mpdf::SCALE * $this->falseBoldWeight);
            $tc = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            if ($this->FillColor != $tc) {
                $s .= $tc . ' ';
            }  // stroke (outline) = same colour as text(fill)
        }
        if (strpos($this->ReqFontStyle, "I") !== false && strpos($this->FontStyle, "I") === false) {
            $aix = '1 0 0.261799 1 %.3F %.3F Tm';
        } else {
            $aix = '%.3F %.3F Td';
        }

        $aix = $aixextra . $aix;

        if ($this->ColorFlag) {
            $s.=$this->TextColor . ' ';
        }

        $this->CurrentFont['used'] = true;

        if ($this->usingCoreFont) {
            $txt2 = str_replace(chr(160), chr(32), $txt);
        } else {
            $txt2 = str_replace(chr(194) . chr(160), chr(32), $txt);
        }

        $px = $x;
        $py = $y;
        if ($coordsys != 'SVG') {
            $px = $x * Mpdf::SCALE;
            $py = ($this->h - $y) * Mpdf::SCALE;
        }


        /** ************** SIMILAR TO Cell() ************************ */

        // IF corefonts AND NOT SmCaps AND NOT Kerning
        // Just output text
        if ($this->usingCoreFont && !($textvar & TextVars::FC_SMALLCAPS) && !($textvar & TextVars::FC_KERNING)) {
            $txt2 = $this->_escape($txt2);
            $s .=sprintf('BT ' . $aix . ' (%s) Tj ET', $px, $py, $txt2);
        } // IF NOT corefonts [AND NO wordspacing] AND NOT SIP/SMP AND NOT SmCaps AND NOT Kerning AND NOT OTL
        // Just output text
        elseif (!$this->usingCoreFont && !($textvar & TextVars::FC_SMALLCAPS) && !($textvar & TextVars::FC_KERNING) && !(isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF) && !empty($OTLdata['GPOSinfo']))) {
            // IF SIP/SMP
            if ($this->CurrentFont['sip'] || $this->CurrentFont['smp']) {
                $txt2 = $this->UTF8toSubset($txt2);
                $s .=sprintf('BT ' . $aix . ' %s Tj ET', $px, $py, $txt2);
            } // NOT SIP/SMP
            else {
                $txt2 = $this->UTF8ToUTF16BE($txt2, false);
                $txt2 = $this->_escape($txt2);
                $s .=sprintf('BT ' . $aix . ' (%s) Tj ET', $px, $py, $txt2);
            }
        } // IF NOT corefonts [AND IS wordspacing] AND NOT SIP AND NOT SmCaps AND NOT Kerning AND NOT OTL
        // Not required here (cf. Cell() )
        // ELSE (IF SmCaps || Kerning || OTL) [corefonts or not corefonts; SIP or SMP or BMP]
        else {
            $s .= $this->applyGPOSpdf($txt2, $aix, $px, $py, $OTLdata, $textvar);
        }
        /*         * ************** END ************************ */

        $s .= ' ';

        if (($textvar & TextVars::FD_UNDERLINE) && $txt != '') { // mPDF 5.7.1
            $c = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            if ($this->FillColor != $c) {
                $s.= ' ' . $c . ' ';
            }
            if (isset($this->CurrentFont['up']) && $this->CurrentFont['up']) {
                $up = $this->CurrentFont['up'];
            } else {
                $up = -100;
            }
            $adjusty = (-$up / 1000 * $this->FontSize);
            if (isset($this->CurrentFont['ut']) && $this->CurrentFont['ut']) {
                $ut = $this->CurrentFont['ut'] / 1000 * $this->FontSize;
            } else {
                $ut = 60 / 1000 * $this->FontSize;
            }
            $olw = $this->LineWidth;
            $s.=' ' . (sprintf(' %.3F w', $ut * Mpdf::SCALE));
            $s.=' ' . $this->_dounderline($x, $y + $adjusty, $txt, $OTLdata, $textvar);
            $s.=' ' . (sprintf(' %.3F w', $olw * Mpdf::SCALE));
            if ($this->FillColor != $c) {
                $s.= ' ' . $this->FillColor . ' ';
            }
        }
        // STRIKETHROUGH
        if (($textvar & TextVars::FD_LINETHROUGH) && $txt != '') { // mPDF 5.7.1
            $c = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            if ($this->FillColor != $c) {
                $s.= ' ' . $c . ' ';
            }
            // Superscript and Subscript Y coordinate adjustment (now for striked-through texts)
            if (isset($this->CurrentFont['desc']['CapHeight']) && $this->CurrentFont['desc']['CapHeight']) {
                $ch = $this->CurrentFont['desc']['CapHeight'];
            } else {
                $ch = 700;
            }
            $adjusty = (-$ch / 1000 * $this->FontSize) * 0.35;
            if (isset($this->CurrentFont['ut']) && $this->CurrentFont['ut']) {
                $ut = $this->CurrentFont['ut'] / 1000 * $this->FontSize;
            } else {
                $ut = 60 / 1000 * $this->FontSize;
            }
            $olw = $this->LineWidth;
            $s.=' ' . (sprintf(' %.3F w', $ut * Mpdf::SCALE));
            $s.=' ' . $this->_dounderline($x, $y + $adjusty, $txt, $OTLdata, $textvar);
            $s.=' ' . (sprintf(' %.3F w', $olw * Mpdf::SCALE));
            if ($this->FillColor != $c) {
                $s.= ' ' . $this->FillColor . ' ';
            }
        }
        $s .= 'Q';

        if ($return) {
            return $s . " \n";
        }
        $this->_out($s);
    }

    /* -- DIRECTW -- */

    public function WriteText($x, $y, $txt)
    {
        // Output a string using Text() but does encoding and text reversing of RTL
        $txt = $this->purify_utf8_text($txt);
        if ($this->text_input_as_HTML) {
            $txt = $this->all_entities_to_utf8($txt);
        }
        if ($this->usingCoreFont) {
            $txt = mb_convert_encoding($txt, $this->mb_enc, 'UTF-8');
        }

        // DIRECTIONALITY
        if (preg_match("/([" . $this->pregRTLchars . "])/u", $txt)) {
            $this->biDirectional = true;
        } // *OTL*

        $textvar = 0;
        $save_OTLtags = $this->OTLtags;
        $this->OTLtags = [];
        if ($this->useKerning) {
            if ($this->CurrentFont['haskernGPOS']) {
                $this->OTLtags['Plus'] .= ' kern';
            } else {
                $textvar = ($textvar | TextVars::FC_KERNING);
            }
        }

        /* -- OTL -- */
        // Use OTL OpenType Table Layout - GSUB & GPOS
        if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
            $txt = $this->otl->applyOTL($txt, $this->CurrentFont['useOTL']);
            $OTLdata = $this->otl->OTLdata;
        }
        /* -- END OTL -- */
        $this->OTLtags = $save_OTLtags;

        $this->magic_reverse_dir($txt, $this->directionality, $OTLdata);

        $this->Text($x, $y, $txt, $OTLdata, $textvar);
    }

    public function WriteCell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0)
    {
        // Output a cell using Cell() but does encoding and text reversing of RTL
        $txt = $this->purify_utf8_text($txt);
        if ($this->text_input_as_HTML) {
            $txt = $this->all_entities_to_utf8($txt);
        }
        if ($this->usingCoreFont) {
            $txt = mb_convert_encoding($txt, $this->mb_enc, 'UTF-8');
        }
        // DIRECTIONALITY
        if (preg_match("/([" . $this->pregRTLchars . "])/u", $txt)) {
            $this->biDirectional = true;
        } // *OTL*

        $textvar = 0;
        $save_OTLtags = $this->OTLtags;
        $this->OTLtags = [];
        if ($this->useKerning) {
            if ($this->CurrentFont['haskernGPOS']) {
                $this->OTLtags['Plus'] .= ' kern';
            } else {
                $textvar = ($textvar | TextVars::FC_KERNING);
            }
        }

        /* -- OTL -- */
        // Use OTL OpenType Table Layout - GSUB & GPOS
        if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
            $txt = $this->otl->applyOTL($txt, $this->CurrentFont['useOTL']);
            $OTLdata = $this->otl->OTLdata;
        }
        /* -- END OTL -- */
        $this->OTLtags = $save_OTLtags;

        $this->magic_reverse_dir($txt, $this->directionality, $OTLdata);

        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $currentx, 0, 0, 'M', 0, false, $OTLdata, $textvar);
    }

    /* -- END DIRECTW -- */

    public function ResetSpacing()
    {
        if ($this->ws != 0) {
            $this->_out('BT 0 Tw ET');
        }
        $this->ws = 0;
        if ($this->charspacing != 0) {
            $this->_out('BT 0 Tc ET');
        }
        $this->charspacing = 0;
    }

    public function SetSpacing($cs, $ws)
    {
        if (intval($cs * 1000) == 0) {
            $cs = 0;
        }
        if ($cs) {
            $this->_out(sprintf('BT %.3F Tc ET', $cs));
        } elseif ($this->charspacing != 0) {
            $this->_out('BT 0 Tc ET');
        }
        $this->charspacing = $cs;
        if (intval($ws * 1000) == 0) {
            $ws = 0;
        }
        if ($ws) {
            $this->_out(sprintf('BT %.3F Tw ET', $ws));
        } elseif ($this->ws != 0) {
            $this->_out('BT 0 Tw ET');
        }
        $this->ws = $ws;
    }

    // WORD SPACING
    public function GetJspacing($nc, $ns, $w, $inclCursive, &$cOTLdata)
    {
        $kashida_present = false;
        $kashida_space = 0;
        if ($w > 0 && $inclCursive && isset($this->CurrentFont['useKashida']) && $this->CurrentFont['useKashida'] && !empty($cOTLdata)) {
            for ($c = 0; $c < count($cOTLdata); $c++) {
                for ($i = 0; $i < strlen($cOTLdata[$c]['group']); $i++) {
                    if (isset($cOTLdata[$c]['GPOSinfo'][$i]['kashida']) && $cOTLdata[$c]['GPOSinfo'][$i]['kashida'] > 0) {
                        $kashida_present = true;
                        break 2;
                    }
                }
            }
        }

        if ($kashida_present) {
            $k_ctr = 0;  // Number of kashida points
            $k_total = 0;  // Total of kashida values (priority)
            // Reset word
            $max_kashida_in_word = 0;
            $last_kashida_in_word = -1;

            for ($c = 0; $c < count($cOTLdata); $c++) {
                for ($i = 0; $i < strlen($cOTLdata[$c]['group']); $i++) {
                    if ($cOTLdata[$c]['group']{$i} == 'S') {
                        // Save from last word
                        if ($max_kashida_in_word) {
                            $k_ctr++;
                            $k_total = $max_kashida_in_word;
                        }
                        // Reset word
                        $max_kashida_in_word = 0;
                        $last_kashida_in_word = -1;
                    }

                    if (isset($cOTLdata[$c]['GPOSinfo'][$i]['kashida']) && $cOTLdata[$c]['GPOSinfo'][$i]['kashida'] > 0) {
                        if ($max_kashida_in_word) {
                            if ($cOTLdata[$c]['GPOSinfo'][$i]['kashida'] > $max_kashida_in_word) {
                                $max_kashida_in_word = $cOTLdata[$c]['GPOSinfo'][$i]['kashida'];
                                $cOTLdata[$c]['GPOSinfo'][$last_kashida_in_word]['kashida'] = 0;
                                $last_kashida_in_word = $i;
                            } else {
                                $cOTLdata[$c]['GPOSinfo'][$i]['kashida'] = 0;
                            }
                        } else {
                            $max_kashida_in_word = $cOTLdata[$c]['GPOSinfo'][$i]['kashida'];
                            $last_kashida_in_word = $i;
                        }
                    }
                }
            }
            // Save from last word
            if ($max_kashida_in_word) {
                $k_ctr++;
                $k_total = $max_kashida_in_word;
            }

            // Number of kashida points = $k_ctr
            // $useKashida is a % value from CurrentFont/config_fonts.php
            // % ratio divided between word-spacing and kashida-spacing
            $kashida_space_ratio = intval($this->CurrentFont['useKashida']) / 100;


            $kashida_space = $w * $kashida_space_ratio;

            $tatw = $this->_getCharWidth($this->CurrentFont['cw'], 0x0640);
            // Only use kashida if each allocated kashida width is > 0.01 x width of a tatweel
            // Otherwise fontstretch is too small and errors
            // If not just leave to adjust word-spacing
            if ($tatw && (($kashida_space / $k_ctr) / $tatw) > 0.01) {
                for ($c = 0; $c < count($cOTLdata); $c++) {
                    for ($i = 0; $i < strlen($cOTLdata[$c]['group']); $i++) {
                        if (isset($cOTLdata[$c]['GPOSinfo'][$i]['kashida']) && $cOTLdata[$c]['GPOSinfo'][$i]['kashida'] > 0) {
                            // At this point kashida is a number representing priority (higher number - higher priority)
                            // We are now going to set it as an actual length
                            // This shares it equally amongst words:
                            $cOTLdata[$c]['GPOSinfo'][$i]['kashida_space'] = (1 / $k_ctr) * $kashida_space;
                        }
                    }
                }
                $w -= $kashida_space;
            }
        }

        $ws = 0;
        $charspacing = 0;
        $ww = $this->jSWord;
        $ncx = $nc - 1;
        if ($nc == 0) {
            return [0, 0, 0];
        } // Only word spacing allowed / possible
        elseif ($this->fixedlSpacing !== false || $inclCursive) {
            if ($ns) {
                $ws = $w / $ns;
            }
        } elseif ($nc == 1) {
            $charspacing = $w;
        } elseif (!$ns) {
            $charspacing = $w / ($ncx );
            if (($this->jSmaxChar > 0) && ($charspacing > $this->jSmaxChar)) {
                $charspacing = $this->jSmaxChar;
            }
        } elseif ($ns == ($ncx )) {
            $charspacing = $w / $ns;
        } else {
            if ($this->usingCoreFont) {
                $cs = ($w * (1 - $this->jSWord)) / ($ncx );
                if (($this->jSmaxChar > 0) && ($cs > $this->jSmaxChar)) {
                    $cs = $this->jSmaxChar;
                    $ww = 1 - (($cs * ($ncx )) / $w);
                }
                $charspacing = $cs;
                $ws = ($w * ($ww) ) / $ns;
            } else {
                $cs = ($w * (1 - $this->jSWord)) / ($ncx - $ns);
                if (($this->jSmaxChar > 0) && ($cs > $this->jSmaxChar)) {
                    $cs = $this->jSmaxChar;
                    $ww = 1 - (($cs * ($ncx - $ns)) / $w);
                }
                $charspacing = $cs;
                $ws = (($w * ($ww) ) / $ns) - $charspacing;
            }
        }
        return [$charspacing, $ws, $kashida_space];
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = 0, $link = '', $currentx = 0, $lcpaddingL = 0, $lcpaddingR = 0, $valign = 'M', $spanfill = 0, $exactWidth = false, $OTLdata = false, $textvar = 0, $lineBox = false)
    {
    // mPDF 5.7.1
        // Output a cell
        // Expects input to be mb_encoded if necessary and RTL reversed
        // NON_BREAKING SPACE
        if ($this->usingCoreFont) {
            $txt = str_replace(chr(160), chr(32), $txt);
        } else {
            $txt = str_replace(chr(194) . chr(160), chr(32), $txt);
        }

        $oldcolumn = $this->CurrCol;
        // Automatic page break
        // Allows PAGE-BREAK-AFTER = avoid to work
        if (isset($this->blk[$this->blklvl])) {
            $bottom = $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['margin_bottom'];
        } else {
            $bottom = 0;
        }
        if (!$this->tableLevel && (($this->y + $this->divheight > $this->PageBreakTrigger) || ($this->y + $h > $this->PageBreakTrigger) ||
            ($this->y + ($h * 2) + $bottom > $this->PageBreakTrigger && $this->blk[$this->blklvl]['page_break_after_avoid'])) and ! $this->InFooter and $this->AcceptPageBreak()) { // mPDF 5.7.2
            $x = $this->x; // Current X position
            // WORD SPACING
            $ws = $this->ws; // Word Spacing
            $charspacing = $this->charspacing; // Character Spacing
            $this->ResetSpacing();

            $this->AddPage($this->CurOrientation);
            // Added to correct for OddEven Margins
            $x += $this->MarginCorrection;
            if ($currentx) {
                $currentx += $this->MarginCorrection;
            }
            $this->x = $x;
            // WORD SPACING
            $this->SetSpacing($charspacing, $ws);
        }

        // Test: to put line through centre of cell: $this->Line($this->x,$this->y+($h/2),$this->x+50,$this->y+($h/2));
        // Test: to put border around cell as it is specified: $border='LRTB';


        /* -- COLUMNS -- */
        // COLS
        // COLUMN CHANGE
        if ($this->CurrCol != $oldcolumn) {
            if ($currentx) {
                $currentx += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
            }
            $this->x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
        }

        // COLUMNS Update/overwrite the lowest bottom of printing y value for a column
        if ($this->ColActive) {
            if ($h) {
                $this->ColDetails[$this->CurrCol]['bottom_margin'] = $this->y + $h;
            } else {
                $this->ColDetails[$this->CurrCol]['bottom_margin'] = $this->y + $this->divheight;
            }
        }
        /* -- END COLUMNS -- */


        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $s = '';
        if ($fill == 1 && $this->FillColor) {
            if ((isset($this->pageoutput[$this->page]['FillColor']) && $this->pageoutput[$this->page]['FillColor'] != $this->FillColor) || !isset($this->pageoutput[$this->page]['FillColor'])) {
                $s .= $this->FillColor . ' ';
            }
            $this->pageoutput[$this->page]['FillColor'] = $this->FillColor;
        }


        if ($lineBox && isset($lineBox['boxtop']) && $txt) { // i.e. always from WriteFlowingBlock/finishFlowingBlock (but not objects -
            // which only have $lineBox['top'] set)
            $boxtop = $this->y + $lineBox['boxtop'];
            $boxbottom = $this->y + $lineBox['boxbottom'];
            $glyphYorigin = $lineBox['glyphYorigin'];
            $baseline_shift = $lineBox['baseline-shift'];
            $bord_boxtop = $bg_boxtop = $boxtop = $boxtop - $baseline_shift;
            $bord_boxbottom = $bg_boxbottom = $boxbottom = $boxbottom - $baseline_shift;
            $bord_boxheight = $bg_boxheight = $boxheight = $boxbottom - $boxtop;

            // If inline element BACKGROUND has bounding box set by parent element:
            if (isset($lineBox['background-boxtop'])) {
                $bg_boxtop = $this->y + $lineBox['background-boxtop'] - $lineBox['background-baseline-shift'];
                $bg_boxbottom = $this->y + $lineBox['background-boxbottom'] - $lineBox['background-baseline-shift'];
                $bg_boxheight = $bg_boxbottom - $bg_boxtop;
            }
            // If inline element BORDER has bounding box set by parent element:
            if (isset($lineBox['border-boxtop'])) {
                $bord_boxtop = $this->y + $lineBox['border-boxtop'] - $lineBox['border-baseline-shift'];
                $bord_boxbottom = $this->y + $lineBox['border-boxbottom'] - $lineBox['border-baseline-shift'];
                $bord_boxheight = $bord_boxbottom - $bord_boxtop;
            }
        } else {
            $boxtop = $this->y;
            $boxheight = $h;
            $boxbottom = $this->y + $h;
            $baseline_shift = 0;
            if ($txt != '') {
                // FONT SIZE - this determines the baseline caculation
                $bfs = $this->FontSize;
                // Calculate baseline Superscript and Subscript Y coordinate adjustment
                $bfx = $this->baselineC;
                $baseline = $bfx * $bfs;

                if ($textvar & TextVars::FA_SUPERSCRIPT) {
                    $baseline_shift = $this->textparam['text-baseline'];
                } // mPDF 5.7.1 // mPDF 6
                elseif ($textvar & TextVars::FA_SUBSCRIPT) {
                    $baseline_shift = $this->textparam['text-baseline'];
                } // mPDF 5.7.1 // mPDF 6
                elseif ($this->bullet) {
                    $baseline += ($bfx - 0.7) * $this->FontSize;
                }

                // Vertical align (for Images)
                if ($valign == 'T') {
                    $va = (0.5 * $bfs * $this->normalLineheight);
                } elseif ($valign == 'B') {
                    $va = $h - (0.5 * $bfs * $this->normalLineheight);
                } else {
                    $va = 0.5 * $h;
                } // Middle
                // ONLY SET THESE IF WANT TO CONFINE BORDER +/- FILL TO FIT FONTSIZE - NOT FULL CELL AS IS ORIGINAL FUNCTION
                // spanfill or spanborder are set in FlowingBlock functions
                if ($spanfill || !empty($this->spanborddet) || $link != '') {
                    $exth = 0.2; // Add to fontsize to increase height of background / link / border
                    $boxtop = $this->y + $baseline + $va - ($this->FontSize * (1 + $exth / 2) * (0.5 + $bfx));
                    $boxheight = $this->FontSize * (1 + $exth);
                    $boxbottom = $boxtop + $boxheight;
                }
                $glyphYorigin = $baseline + $va;
            }
            $boxtop -= $baseline_shift;
            $boxbottom -= $baseline_shift;
            $bord_boxtop = $bg_boxtop = $boxtop;
            $bord_boxbottom = $bg_boxbottom = $boxbottom;
            $bord_boxheight = $bg_boxheight = $boxheight = $boxbottom - $boxtop;
        }


        $bbw = $tbw = $lbw = $rbw = 0; // Border widths
        if (!empty($this->spanborddet)) {
            if (!isset($this->spanborddet['B'])) {
                $this->spanborddet['B'] = ['s' => 0, 'style' => '', 'w' => 0];
            }
            if (!isset($this->spanborddet['T'])) {
                $this->spanborddet['T'] = ['s' => 0, 'style' => '', 'w' => 0];
            }
            if (!isset($this->spanborddet['L'])) {
                $this->spanborddet['L'] = ['s' => 0, 'style' => '', 'w' => 0];
            }
            if (!isset($this->spanborddet['R'])) {
                $this->spanborddet['R'] = ['s' => 0, 'style' => '', 'w' => 0];
            }
            $bbw = $this->spanborddet['B']['w'];
            $tbw = $this->spanborddet['T']['w'];
            $lbw = $this->spanborddet['L']['w'];
            $rbw = $this->spanborddet['R']['w'];
        }
        if ($fill == 1 || $border == 1 || !empty($this->spanborddet)) {
            if (!empty($this->spanborddet)) {
                if ($fill == 1) {
                    $s.=sprintf('%.3F %.3F %.3F %.3F re f ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bg_boxtop + $tbw) * Mpdf::SCALE, ($w + $lbw + $rbw) * Mpdf::SCALE, (-$bg_boxheight - $tbw - $bbw) * Mpdf::SCALE);
                }
                $s.= ' q ';
                $dashon = 3;
                $dashoff = 3.5;
                $dot = 2.5;
                if ($tbw) {
                    $short = 0;
                    if ($this->spanborddet['T']['style'] == 'dashed') {
                        $s.=sprintf(' 0 j 0 J [%.3F %.3F] 0 d ', $tbw * $dashon * Mpdf::SCALE, $tbw * $dashoff * Mpdf::SCALE);
                    } elseif ($this->spanborddet['T']['style'] == 'dotted') {
                        $s.=sprintf(' 1 j 1 J [%.3F %.3F] %.3F d ', 0.001, $tbw * $dot * Mpdf::SCALE, -$tbw / 2 * Mpdf::SCALE);
                        $short = $tbw / 2;
                    } else {
                        $s.=' 0 j 0 J [] 0 d ';
                    }
                    if ($this->spanborddet['T']['style'] != 'dotted') {
                        $s .= 'q ';
                        $s .= sprintf('%.3F %.3F m ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE);
                        $s .= ' h W n '; // Ends path no-op & Sets the clipping path
                    }
                    $c = $this->SetDColor($this->spanborddet['T']['c'], true);
                    if ($this->spanborddet['T']['style'] == 'double') {
                        $s.=sprintf(' %s %.3F w ', $c, $tbw / 3 * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw * 5 / 6) * Mpdf::SCALE, ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw * 5 / 6) * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 6) * Mpdf::SCALE, ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 6) * Mpdf::SCALE);
                    } elseif ($this->spanborddet['T']['style'] == 'dotted') {
                        $s.=sprintf(' %s %.3F w ', $c, $tbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 2) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 2) * Mpdf::SCALE);
                    } else {
                        $s.=sprintf(' %s %.3F w ', $c, $tbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 2) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw / 2) * Mpdf::SCALE);
                    }
                    if ($this->spanborddet['T']['style'] != 'dotted') {
                        $s .= ' Q ';
                    }
                }
                if ($bbw) {
                    $short = 0;
                    if ($this->spanborddet['B']['style'] == 'dashed') {
                        $s.=sprintf(' 0 j 0 J [%.3F %.3F] 0 d ', $bbw * $dashon * Mpdf::SCALE, $bbw * $dashoff * Mpdf::SCALE);
                    } elseif ($this->spanborddet['B']['style'] == 'dotted') {
                        $s.=sprintf(' 1 j 1 J [%.3F %.3F] %.3F d ', 0.001, $bbw * $dot * Mpdf::SCALE, -$bbw / 2 * Mpdf::SCALE);
                        $short = $bbw / 2;
                    } else {
                        $s.=' 0 j 0 J [] 0 d ';
                    }
                    if ($this->spanborddet['B']['style'] != 'dotted') {
                        $s .= 'q ';
                        $s .= sprintf('%.3F %.3F m ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w) * Mpdf::SCALE, ($this->h - $bord_boxbottom) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x) * Mpdf::SCALE, ($this->h - $bord_boxbottom) * Mpdf::SCALE);
                        $s .= ' h W n '; // Ends path no-op & Sets the clipping path
                    }
                    $c = $this->SetDColor($this->spanborddet['B']['c'], true);
                    if ($this->spanborddet['B']['style'] == 'double') {
                        $s.=sprintf(' %s %.3F w ', $c, $bbw / 3 * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 6) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 6) * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw * 5 / 6) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw * 5 / 6) * Mpdf::SCALE);
                    } elseif ($this->spanborddet['B']['style'] == 'dotted') {
                        $s.=sprintf(' %s %.3F w ', $c, $bbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 2) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 2) * Mpdf::SCALE);
                    } else {
                        $s.=sprintf(' %s %.3F w ', $c, $bbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 2) * Mpdf::SCALE, ($this->x + $w + $rbw - $short) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw / 2) * Mpdf::SCALE);
                    }
                    if ($this->spanborddet['B']['style'] != 'dotted') {
                        $s .= ' Q ';
                    }
                }
                if ($lbw) {
                    $short = 0;
                    if ($this->spanborddet['L']['style'] == 'dashed') {
                        $s.=sprintf(' 0 j 0 J [%.3F %.3F] 0 d ', $lbw * $dashon * Mpdf::SCALE, $lbw * $dashoff * Mpdf::SCALE);
                    } elseif ($this->spanborddet['L']['style'] == 'dotted') {
                        $s.=sprintf(' 1 j 1 J [%.3F %.3F] %.3F d ', 0.001, $lbw * $dot * Mpdf::SCALE, -$lbw / 2 * Mpdf::SCALE);
                        $short = $lbw / 2;
                    } else {
                        $s.=' 0 j 0 J [] 0 d ';
                    }
                    if ($this->spanborddet['L']['style'] != 'dotted') {
                        $s .= 'q ';
                        $s .= sprintf('%.3F %.3F m ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x) * Mpdf::SCALE, ($this->h - $bord_boxbottom) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x - $lbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE);
                        $s .= ' h W n '; // Ends path no-op & Sets the clipping path
                    }
                    $c = $this->SetDColor($this->spanborddet['L']['c'], true);
                    if ($this->spanborddet['L']['style'] == 'double') {
                        $s.=sprintf(' %s %.3F w ', $c, $lbw / 3 * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw / 6) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x - $lbw / 6) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw * 5 / 6) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x - $lbw * 5 / 6) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    } elseif ($this->spanborddet['L']['style'] == 'dotted') {
                        $s.=sprintf(' %s %.3F w ', $c, $lbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x - $lbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    } else {
                        $s.=sprintf(' %s %.3F w ', $c, $lbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x - $lbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x - $lbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    }
                    if ($this->spanborddet['L']['style'] != 'dotted') {
                        $s .= ' Q ';
                    }
                }
                if ($rbw) {
                    $short = 0;
                    if ($this->spanborddet['R']['style'] == 'dashed') {
                        $s.=sprintf(' 0 j 0 J [%.3F %.3F] 0 d ', $rbw * $dashon * Mpdf::SCALE, $rbw * $dashoff * Mpdf::SCALE);
                    } elseif ($this->spanborddet['R']['style'] == 'dotted') {
                        $s.=sprintf(' 1 j 1 J [%.3F %.3F] %.3F d ', 0.001, $rbw * $dot * Mpdf::SCALE, -$rbw / 2 * Mpdf::SCALE);
                        $short = $rbw / 2;
                    } else {
                        $s.=' 0 j 0 J [] 0 d ';
                    }
                    if ($this->spanborddet['R']['style'] != 'dotted') {
                        $s .= 'q ';
                        $s .= sprintf('%.3F %.3F m ', ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w) * Mpdf::SCALE, ($this->h - $bord_boxbottom) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE);
                        $s .= sprintf('%.3F %.3F l ', ($this->x + $w + $rbw) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE);
                        $s .= ' h W n '; // Ends path no-op & Sets the clipping path
                    }
                    $c = $this->SetDColor($this->spanborddet['R']['c'], true);
                    if ($this->spanborddet['R']['style'] == 'double') {
                        $s.=sprintf(' %s %.3F w ', $c, $rbw / 3 * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x + $w + $rbw / 6) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x + $w + $rbw / 6) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x + $w + $rbw * 5 / 6) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x + $w + $rbw * 5 / 6) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    } elseif ($this->spanborddet['R']['style'] == 'dotted') {
                        $s.=sprintf(' %s %.3F w ', $c, $rbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x + $w + $rbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x + $w + $rbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    } else {
                        $s.=sprintf(' %s %.3F w ', $c, $rbw * Mpdf::SCALE);
                        $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($this->x + $w + $rbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxtop + $tbw) * Mpdf::SCALE, ($this->x + $w + $rbw / 2) * Mpdf::SCALE, ($this->h - $bord_boxbottom - $bbw + $short) * Mpdf::SCALE);
                    }
                    if ($this->spanborddet['R']['style'] != 'dotted') {
                        $s .= ' Q ';
                    }
                }
                $s.= ' Q ';
            } else { // If "border", does not come from WriteFlowingBlock or FinishFlowingBlock
                if ($fill == 1) {
                    $op = ($border == 1) ? 'B' : 'f';
                } else {
                    $op = 'S';
                }
                $s.=sprintf('%.3F %.3F %.3F %.3F re %s ', $this->x * Mpdf::SCALE, ($this->h - $bg_boxtop) * Mpdf::SCALE, $w * Mpdf::SCALE, -$bg_boxheight * Mpdf::SCALE, $op);
            }
        }

        if (is_string($border)) { // If "border", does not come from WriteFlowingBlock or FinishFlowingBlock
            $x = $this->x;
            $y = $this->y;
            if (is_int(strpos($border, 'L'))) {
                $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', $x * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE, $x * Mpdf::SCALE, ($this->h - ($bord_boxbottom)) * Mpdf::SCALE);
            }
            if (is_int(strpos($border, 'T'))) {
                $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', $x * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE, ($x + $w) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE);
            }
            if (is_int(strpos($border, 'R'))) {
                $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', ($x + $w) * Mpdf::SCALE, ($this->h - $bord_boxtop) * Mpdf::SCALE, ($x + $w) * Mpdf::SCALE, ($this->h - ($bord_boxbottom)) * Mpdf::SCALE);
            }
            if (is_int(strpos($border, 'B'))) {
                $s.=sprintf('%.3F %.3F m %.3F %.3F l S ', $x * Mpdf::SCALE, ($this->h - ($bord_boxbottom)) * Mpdf::SCALE, ($x + $w) * Mpdf::SCALE, ($this->h - ($bord_boxbottom)) * Mpdf::SCALE);
            }
        }

        if ($txt != '') {
            if ($exactWidth) {
                $stringWidth = $w;
            } else {
                $stringWidth = $this->GetStringWidth($txt, true, $OTLdata, $textvar) + ( $this->charspacing * mb_strlen($txt, $this->mb_enc) / Mpdf::SCALE ) + ( $this->ws * mb_substr_count($txt, ' ', $this->mb_enc) / Mpdf::SCALE );
            }

            // Set x OFFSET FOR PRINTING
            if ($align == 'R') {
                $dx = $w - $this->cMarginR - $stringWidth - $lcpaddingR;
            } elseif ($align == 'C') {
                $dx = (($w - $stringWidth ) / 2);
            } elseif ($align == 'L' or $align == 'J') {
                $dx = $this->cMarginL + $lcpaddingL;
            } else {
                $dx = 0;
            }

            if ($this->ColorFlag) {
                $s .='q ' . $this->TextColor . ' ';
            }

            // OUTLINE
            if (isset($this->textparam['outline-s']) && $this->textparam['outline-s'] && !($textvar & TextVars::FC_SMALLCAPS)) { // mPDF 5.7.1
                $s .=' ' . sprintf('%.3F w', $this->LineWidth * Mpdf::SCALE) . ' ';
                $s .=" $this->DrawColor ";
                $s .=" 2 Tr ";
            } elseif ($this->falseBoldWeight && strpos($this->ReqFontStyle, "B") !== false && strpos($this->FontStyle, "B") === false && !($textvar & TextVars::FC_SMALLCAPS)) { // can't use together with OUTLINE or Small Caps   // mPDF 5.7.1   ??? why not with SmallCaps ???
                $s .= ' 2 Tr 1 J 1 j ';
                $s .= ' ' . sprintf('%.3F w', ($this->FontSize / 130) * Mpdf::SCALE * $this->falseBoldWeight) . ' ';
                $tc = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
                if ($this->FillColor != $tc) {
                    $s .= ' ' . $tc . ' ';
                }  // stroke (outline) = same colour as text(fill)
            } else {
                $s .=" 0 Tr ";
            }

            if (strpos($this->ReqFontStyle, "I") !== false && strpos($this->FontStyle, "I") === false) { // Artificial italic
                $aix = '1 0 0.261799 1 %.3F %.3F Tm ';
            } else {
                $aix = '%.3F %.3F Td ';
            }

            $px = ($this->x + $dx) * Mpdf::SCALE;
            $py = ($this->h - ($this->y + $glyphYorigin - $baseline_shift)) * Mpdf::SCALE;

            // THE TEXT
            $txt2 = $txt;
            $sub = '';
            $this->CurrentFont['used'] = true;

            /*             * ************** SIMILAR TO Text() ************************ */

            // IF corefonts AND NOT SmCaps AND NOT Kerning
            // Just output text; charspacing and wordspacing already set by charspacing (Tc) and ws (Tw)
            if ($this->usingCoreFont && !($textvar & TextVars::FC_SMALLCAPS) && !($textvar & TextVars::FC_KERNING)) {
                $txt2 = $this->_escape($txt2);
                $sub .=sprintf('BT ' . $aix . ' (%s) Tj ET', $px, $py, $txt2);
            } // IF NOT corefonts AND NO wordspacing AND NOT SIP/SMP AND NOT SmCaps AND NOT Kerning AND NOT OTL
            // Just output text
            elseif (!$this->usingCoreFont && !$this->ws && !($textvar & TextVars::FC_SMALLCAPS) && !($textvar & TextVars::FC_KERNING) && !(isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF) && !empty($OTLdata['GPOSinfo']))) {
                // IF SIP/SMP
                if ((isset($this->CurrentFont['sip']) && $this->CurrentFont['sip']) || (isset($this->CurrentFont['smp']) && $this->CurrentFont['smp'])) {
                    $txt2 = $this->UTF8toSubset($txt2);
                    $sub .=sprintf('BT ' . $aix . ' %s Tj ET', $px, $py, $txt2);
                } // NOT SIP/SMP
                else {
                    $txt2 = $this->UTF8ToUTF16BE($txt2, false);
                    $txt2 = $this->_escape($txt2);
                    $sub .=sprintf('BT ' . $aix . ' (%s) Tj ET', $px, $py, $txt2);
                }
            } // IF NOT corefonts AND IS wordspacing AND NOT SIP AND NOT SmCaps AND NOT Kerning AND NOT OTL
            // Output text word by word with an adjustment to the intercharacter spacing for SPACEs to form word spacing
            // IF multibyte - Tw has no effect - need to do word spacing using an adjustment before each space
            elseif (!$this->usingCoreFont && $this->ws && !((isset($this->CurrentFont['sip']) && $this->CurrentFont['sip']) || (isset($this->CurrentFont['smp']) && $this->CurrentFont['smp'])) && !($textvar & TextVars::FC_SMALLCAPS) && !($textvar & TextVars::FC_KERNING) && !(isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF) && (!empty($OTLdata['GPOSinfo']) || (strpos($OTLdata['group'], 'M') !== false && $this->charspacing)) )) {
                $space = " ";
                $space = $this->UTF8ToUTF16BE($space, false);
                $space = $this->_escape($space);
                $sub .=sprintf('BT ' . $aix . ' %.3F Tc [', $px, $py, $this->charspacing);
                $t = explode(' ', $txt2);
                $numt = count($t);
                for ($i = 0; $i < $numt; $i++) {
                    $tx = $t[$i];
                    $tx = $this->UTF8ToUTF16BE($tx, false);
                    $tx = $this->_escape($tx);
                    $sub .=sprintf('(%s) ', $tx);
                    if (($i + 1) < $numt) {
                        $adj = -($this->ws) * 1000 / $this->FontSizePt;
                        $sub .=sprintf('%d(%s) ', $adj, $space);
                    }
                }
                $sub .='] TJ ';
                $sub .=' ET';
            } // ELSE (IF SmCaps || Kerning || OTL) [corefonts or not corefonts; SIP or SMP or BMP]
            else {
                $sub = $this->applyGPOSpdf($txt, $aix, $px, $py, $OTLdata, $textvar);
            }

            /** ************** END SIMILAR TO Text() ************************ */

            if ($this->shrin_k > 1) {
                $shrin_k = $this->shrin_k;
            } else {
                $shrin_k = 1;
            }

            // UNDERLINE
            if ($textvar & TextVars::FD_UNDERLINE) { // mPDF 5.7.1  // mPDF 6

                // mPDF 5.7.3  inline text-decoration parameters

                $c = isset($this->textparam['u-decoration']['color']) ? $this->textparam['u-decoration']['color'] : '';
                if ($this->FillColor != $c) {
                    $sub .= ' ' . $c . ' ';
                }

                // mPDF 5.7.3  inline text-decoration parameters
                $decorationfontkey = isset($this->textparam['u-decoration']['fontkey']) ? $this->textparam['u-decoration']['fontkey'] : '';
                $decorationfontsize = isset($this->textparam['u-decoration']['fontsize']) ? $this->textparam['u-decoration']['fontsize'] / $shrin_k : 0;

                if (isset($this->fonts[$decorationfontkey]['ut']) && $this->fonts[$decorationfontkey]['ut']) {
                    $ut = $this->fonts[$decorationfontkey]['ut'] / 1000 * $decorationfontsize;
                } else {
                    $ut = 60 / 1000 * $decorationfontsize;
                }

                if (isset($this->fonts[$decorationfontkey]['up']) && $this->fonts[$decorationfontkey]['up']) {
                    $up = $this->fonts[$decorationfontkey]['up'];
                } else {
                    $up = -100;
                }

                $adjusty = (-$up / 1000 * $decorationfontsize) + $ut / 2;
                $ubaseline = isset($this->textparam['u-decoration']['baseline'])
                    ? $glyphYorigin - $this->textparam['u-decoration']['baseline'] / $shrin_k
                    : $glyphYorigin;

                $olw = $this->LineWidth;

                $sub .= ' ' . (sprintf(' %.3F w 0 j 0 J ', $ut * Mpdf::SCALE));
                $sub .= ' ' . $this->_dounderline($this->x + $dx, $this->y + $ubaseline + $adjusty, $txt, $OTLdata, $textvar);
                $sub .= ' ' . (sprintf(' %.3F w 2 j 2 J ', $olw * Mpdf::SCALE));

                if ($this->FillColor != $c) {
                    $sub .= ' ' . $this->FillColor . ' ';
                }
            }

            // STRIKETHROUGH
            if ($textvar & TextVars::FD_LINETHROUGH) { // mPDF 5.7.1    // mPDF 6

                // mPDF 5.7.3  inline text-decoration parameters
                $c = $this->textparam['s-decoration']['color'];

                if ($this->FillColor != $c) {
                    $sub .= ' ' . $c . ' ';
                }

                // mPDF 5.7.3  inline text-decoration parameters
                $decorationfontkey = $this->textparam['s-decoration']['fontkey'];
                $decorationfontsize = $this->textparam['s-decoration']['fontsize'] / $shrin_k;

                // Use yStrikeoutSize from OS/2 if available
                if (isset($this->fonts[$decorationfontkey]['strs']) && $this->fonts[$decorationfontkey]['strs']) {
                    $ut = $this->fonts[$decorationfontkey]['strs'] / 1000 * $decorationfontsize;
                } // else use underlineThickness from post if available
                elseif (isset($this->fonts[$decorationfontkey]['ut']) && $this->fonts[$decorationfontkey]['ut']) {
                    $ut = $this->fonts[$decorationfontkey]['ut'] / 1000 * $decorationfontsize;
                } else {
                    $ut = 50 / 1000 * $decorationfontsize;
                }

                // Use yStrikeoutPosition from OS/2 if available
                if (isset($this->fonts[$decorationfontkey]['strp']) && $this->fonts[$decorationfontkey]['strp']) {
                    $up = $this->fonts[$decorationfontkey]['strp'];
                    $adjusty = (-$up / 1000 * $decorationfontsize);
                } // else use a fraction ($this->baselineS) of CapHeight
                else {
                    if (isset($this->fonts[$decorationfontkey]['desc']['CapHeight']) && $this->fonts[$decorationfontkey]['desc']['CapHeight']) {
                        $ch = $this->fonts[$decorationfontkey]['desc']['CapHeight'];
                    } else {
                        $ch = 700;
                    }
                    $adjusty = (-$ch / 1000 * $decorationfontsize) * $this->baselineS;
                }

                $sbaseline = $glyphYorigin - $this->textparam['s-decoration']['baseline'] / $shrin_k;

                $olw = $this->LineWidth;

                $sub .=' ' . (sprintf(' %.3F w 0 j 0 J ', $ut * Mpdf::SCALE));
                $sub .=' ' . $this->_dounderline($this->x + $dx, $this->y + $sbaseline + $adjusty, $txt, $OTLdata, $textvar);
                $sub .=' ' . (sprintf(' %.3F w 2 j 2 J ', $olw * Mpdf::SCALE));

                if ($this->FillColor != $c) {
                    $sub .= ' ' . $this->FillColor . ' ';
                }
            }

            // mPDF 5.7.3  inline text-decoration parameters
            // OVERLINE
            if ($textvar & TextVars::FD_OVERLINE) { // mPDF 5.7.1   // mPDF 6
                // mPDF 5.7.3  inline text-decoration parameters
                $c = $this->textparam['o-decoration']['color'];
                if ($this->FillColor != $c) {
                    $sub .= ' ' . $c . ' ';
                }

                // mPDF 5.7.3  inline text-decoration parameters
                $decorationfontkey = (int) (((float) $this->textparam['o-decoration']['fontkey']) / $shrin_k);
                $decorationfontsize = $this->textparam['o-decoration']['fontsize'];

                if (isset($this->fonts[$decorationfontkey]['ut']) && $this->fonts[$decorationfontkey]['ut']) {
                    $ut = $this->fonts[$decorationfontkey]['ut'] / 1000 * $decorationfontsize;
                } else {
                    $ut = 60 / 1000 * $decorationfontsize;
                }
                if (isset($this->fonts[$decorationfontkey]['desc']['CapHeight']) && $this->fonts[$decorationfontkey]['desc']['CapHeight']) {
                    $ch = $this->fonts[$decorationfontkey]['desc']['CapHeight'];
                } else {
                    $ch = 700;
                }
                $adjusty = (-$ch / 1000 * $decorationfontsize) * $this->baselineO;
                $obaseline = $glyphYorigin - $this->textparam['o-decoration']['baseline'] / $shrin_k;
                $olw = $this->LineWidth;
                $sub .=' ' . (sprintf(' %.3F w 0 j 0 J ', $ut * Mpdf::SCALE));
                $sub .=' ' . $this->_dounderline($this->x + $dx, $this->y + $obaseline + $adjusty, $txt, $OTLdata, $textvar);
                $sub .=' ' . (sprintf(' %.3F w 2 j 2 J ', $olw * Mpdf::SCALE));
                if ($this->FillColor != $c) {
                    $sub .= ' ' . $this->FillColor . ' ';
                }
            }

            // TEXT SHADOW
            if ($this->textshadow) {  // First to process is last in CSS comma separated shadows
                foreach ($this->textshadow as $ts) {
                    $s .= ' q ';
                    $s .= $this->SetTColor($ts['col'], true) . "\n";
                    if ($ts['col']{0} == 5 && ord($ts['col']{4}) < 100) { // RGBa
                        $s .= $this->SetAlpha(ord($ts['col']{4}) / 100, 'Normal', true, 'F') . "\n";
                    } elseif ($ts['col']{0} == 6 && ord($ts['col']{5}) < 100) { // CMYKa
                        $s .= $this->SetAlpha(ord($ts['col']{5}) / 100, 'Normal', true, 'F') . "\n";
                    } elseif ($ts['col']{0} == 1 && $ts['col']{2} == 1 && ord($ts['col']{3}) < 100) { // Gray
                        $s .= $this->SetAlpha(ord($ts['col']{3}) / 100, 'Normal', true, 'F') . "\n";
                    }
                    $s .= sprintf(' 1 0 0 1 %.4F %.4F cm', $ts['x'] * Mpdf::SCALE, -$ts['y'] * Mpdf::SCALE) . "\n";
                    $s .= $sub;
                    $s .= ' Q ';
                }
            }

            $s .= $sub;

            // COLOR
            if ($this->ColorFlag) {
                $s .=' Q';
            }

            // LINK
            if ($link != '') {
                $this->Link($this->x, $boxtop, $w, $boxheight, $link);
            }
        }
        if ($s) {
            $this->_out($s);
        }

        // WORD SPACING
        if ($this->ws && !$this->usingCoreFont) {
            $this->_out(sprintf('BT %.3F Tc ET', $this->charspacing));
        }
        $this->lasth = $h;
        if (strpos($txt, "\n") !== false) {
            $ln = 1; // cell recognizes \n from <BR> tag
        }
        if ($ln > 0) {
            // Go to next line
            $this->y += $h;
            if ($ln == 1) {
                // Move to next line
                if ($currentx != 0) {
                    $this->x = $currentx;
                } else {
                    $this->x = $this->lMargin;
                }
            }
        } else {
            $this->x+=$w;
        }
    }

    public function applyGPOSpdf($txt, $aix, $x, $y, $OTLdata, $textvar = 0)
    {
        // Generate PDF string
        // ==============================
        if ((isset($this->CurrentFont['sip']) && $this->CurrentFont['sip']) || (isset($this->CurrentFont['smp']) && $this->CurrentFont['smp'])) {
            $sipset = true;
        } else {
            $sipset = false;
        }

        if ($textvar & TextVars::FC_SMALLCAPS) {
            $smcaps = true;
        } // IF SmallCaps using transformation, NOT OTL
        else {
            $smcaps = false;
        }

        if ($sipset) {
            $fontid = $last_fontid = $original_fontid = $this->CurrentFont['subsetfontids'][0];
        } else {
            $fontid = $last_fontid = $original_fontid = $this->CurrentFont['i'];
        }
        $SmallCapsON = false;  // state: uppercase/not
        $lastSmallCapsON = false; // state: uppercase/not
        $last_fontsize = $fontsize = $this->FontSizePt;
        $last_fontstretch = $fontstretch = 100;
        $groupBreak = false;

        $unicode = $this->UTF8StringToArray($txt);

        $GPOSinfo = (isset($OTLdata['GPOSinfo']) ? $OTLdata['GPOSinfo'] : []);
        $charspacing = ($this->charspacing * 1000 / $this->FontSizePt);
        $wordspacing = ($this->ws * 1000 / $this->FontSizePt);

        $XshiftBefore = 0;
        $XshiftAfter = 0;
        $lastYPlacement = 0;

        if ($sipset) {
            // mPDF 6  DELETED ********
            //  $txt= preg_replace('/'.preg_quote($this->aliasNbPg,'/').'/', chr(7), $txt); // ? Need to adjust OTL info
            //  $txt= preg_replace('/'.preg_quote($this->aliasNbPgGp,'/').'/', chr(8), $txt);   // ? Need to adjust OTL info
            $tj = '<';
        } else {
            $tj = '(';
        }

        for ($i = 0; $i < count($unicode); $i++) {
            $c = $unicode[$i];
            $tx = '';
            $XshiftBefore = $XshiftAfter;
            $XshiftAfter = 0;
            $YPlacement = 0;
            $groupBreak = false;
            $kashida = 0;
            if (!empty($OTLdata)) {
                // YPlacement from GPOS
                if (isset($GPOSinfo[$i]['YPlacement']) && $GPOSinfo[$i]['YPlacement']) {
                    $YPlacement = $GPOSinfo[$i]['YPlacement'] * $this->FontSizePt / $this->CurrentFont['unitsPerEm'];
                    $groupBreak = true;
                }
                // XPlacement from GPOS
                if (isset($GPOSinfo[$i]['XPlacement']) && $GPOSinfo[$i]['XPlacement']) {
                    if (!isset($GPOSinfo[$i]['wDir']) || $GPOSinfo[$i]['wDir'] != 'RTL') {
                        if (isset($GPOSinfo[$i]['BaseWidth'])) {
                            $GPOSinfo[$i]['XPlacement'] -= $GPOSinfo[$i]['BaseWidth'];
                        }
                    }

                    // Convert to PDF Text space (thousandths of a unit );
                    $XshiftBefore += $GPOSinfo[$i]['XPlacement'] * 1000 / $this->CurrentFont['unitsPerEm'];
                    $XshiftAfter += -$GPOSinfo[$i]['XPlacement'] * 1000 / $this->CurrentFont['unitsPerEm'];
                }

                // Kashida from GPOS
                // Kashida is set as an absolute length value, but to adjust text needs to be converted to
                // font-related size
                if (isset($GPOSinfo[$i]['kashida_space']) && $GPOSinfo[$i]['kashida_space']) {
                    $kashida = $GPOSinfo[$i]['kashida_space'];
                }

                if ($c == 32) { // word spacing
                    $XshiftAfter += $wordspacing;
                }

                if (substr($OTLdata['group'], ($i + 1), 1) != 'M') { // Don't add inter-character spacing before Marks
                    $XshiftAfter += $charspacing;
                }

                // ...applyGPOSpdf...
                // XAdvance from GPOS - Convert to PDF Text space (thousandths of a unit );
                if (((isset($GPOSinfo[$i]['wDir']) && $GPOSinfo[$i]['wDir'] != 'RTL') || !isset($GPOSinfo[$i]['wDir'])) && isset($GPOSinfo[$i]['XAdvanceL']) && $GPOSinfo[$i]['XAdvanceL']) {
                    $XshiftAfter += $GPOSinfo[$i]['XAdvanceL'] * 1000 / $this->CurrentFont['unitsPerEm'];
                } elseif (isset($GPOSinfo[$i]['wDir']) && $GPOSinfo[$i]['wDir'] == 'RTL' && isset($GPOSinfo[$i]['XAdvanceR']) && $GPOSinfo[$i]['XAdvanceR']) {
                    $XshiftAfter += $GPOSinfo[$i]['XAdvanceR'] * 1000 / $this->CurrentFont['unitsPerEm'];
                }
            } // Character & Word spacing - if NOT OTL
            else {
                $XshiftAfter += $charspacing;
                if ($c == 32) {
                    $XshiftAfter += $wordspacing;
                }
            }

            // IF Kerning done using pairs rather than OTL
            if ($textvar & TextVars::FC_KERNING) {
                if ($i > 0 && isset($this->CurrentFont['kerninfo'][$unicode[($i - 1)]][$unicode[$i]])) {
                    $XshiftBefore += $this->CurrentFont['kerninfo'][$unicode[($i - 1)]][$unicode[$i]];
                }
            }

            if ($YPlacement != $lastYPlacement) {
                $groupBreak = true;
            }

            if ($XshiftBefore) {  // +ve value in PDF moves to the left
                // If Fontstretch is ongoing, need to adjust X adjustments because these will be stretched out.
                $XshiftBefore *= 100 / $last_fontstretch;
                if ($sipset) {
                    $tj .= sprintf('>%d<', (-$XshiftBefore));
                } else {
                    $tj .= sprintf(')%d(', (-$XshiftBefore));
                }
            }

            // Small-Caps
            if ($smcaps) {
                if (isset($this->upperCase[$c])) {
                    $c = $this->upperCase[$c];
                    // $this->CurrentFont['subset'][$this->upperCase[$c]] = $this->upperCase[$c];   // add the CAP to subset
                    $SmallCapsON = true;
                    // For $sipset
                    if (!$lastSmallCapsON) {   // Turn ON SmallCaps
                        $groupBreak = true;
                        $fontstretch = $this->smCapsStretch;
                        $fontsize = $this->FontSizePt * $this->smCapsScale;
                    }
                } else {
                    $SmallCapsON = false;
                    if ($lastSmallCapsON) {  // Turn OFF SmallCaps
                        $groupBreak = true;
                        $fontstretch = 100;
                        $fontsize = $this->FontSizePt;
                    }
                }
            }

            // Prepare Text and Select Font ID
            if ($sipset) {
                // mPDF 6  DELETED ********
                // if ($c == 7 || $c == 8) {
                // if ($original_fontid != $last_fontid) {
                //  $groupBreak = true;
                //  $fontid = $original_fontid;
                // }
                // if ($c == 7) { $tj .= $this->aliasNbPgHex; }
                // else { $tj .= $this->aliasNbPgGpHex; }
                // continue;
                // }
                for ($j = 0; $j < 99; $j++) {
                    $init = array_search($c, $this->CurrentFont['subsets'][$j]);
                    if ($init !== false) {
                        if ($this->CurrentFont['subsetfontids'][$j] != $last_fontid) {
                            $groupBreak = true;
                            $fontid = $this->CurrentFont['subsetfontids'][$j];
                        }
                        $tx = sprintf("%02s", strtoupper(dechex($init)));
                        break;
                    } elseif (count($this->CurrentFont['subsets'][$j]) < 255) {
                        $n = count($this->CurrentFont['subsets'][$j]);
                        $this->CurrentFont['subsets'][$j][$n] = $c;
                        if ($this->CurrentFont['subsetfontids'][$j] != $last_fontid) {
                            $groupBreak = true;
                            $fontid = $this->CurrentFont['subsetfontids'][$j];
                        }
                        $tx = sprintf("%02s", strtoupper(dechex($n)));
                        break;
                    } elseif (!isset($this->CurrentFont['subsets'][($j + 1)])) {
                        $this->CurrentFont['subsets'][($j + 1)] = [0 => 0];
                        $this->CurrentFont['subsetfontids'][($j + 1)] = count($this->fonts) + $this->extraFontSubsets + 1;
                        $this->extraFontSubsets++;
                    }
                }
            } else {
                $tx = UtfString::code2utf($c);
                if ($this->usingCoreFont) {
                    $tx = utf8_decode($tx);
                } else {
                    $tx = $this->UTF8ToUTF16BE($tx, false);
                }
                $tx = $this->_escape($tx);
            }

            // If any settings require a new Text Group
            if ($groupBreak || $fontstretch != $last_fontstretch) {
                if ($sipset) {
                    $tj .= '>] TJ ';
                } else {
                    $tj .= ')] TJ ';
                }
                if ($fontid != $last_fontid || $fontsize != $last_fontsize) {
                    $tj .= sprintf(' /F%d %.3F Tf ', $fontid, $fontsize);
                }
                if ($fontstretch != $last_fontstretch) {
                    $tj .= sprintf('%d Tz ', $fontstretch);
                }
                if ($YPlacement != $lastYPlacement) {
                    $tj .= sprintf('%.3F Ts ', $YPlacement);
                }
                if ($sipset) {
                    $tj .= '[<';
                } else {
                    $tj .= '[(';
                }
            }

            // Output the code for the txt character
            $tj .= $tx;
            $lastSmallCapsON = $SmallCapsON;
            $last_fontid = $fontid;
            $last_fontsize = $fontsize;
            $last_fontstretch = $fontstretch;

            // Kashida
            if ($kashida) {
                $c = 0x0640; // add the Tatweel U+0640
                if (isset($this->CurrentFont['subset'])) {
                    $this->CurrentFont['subset'][$c] = $c;
                }
                $kashida *= 1000 / $this->FontSizePt;
                $tatw = $this->_getCharWidth($this->CurrentFont['cw'], 0x0640);

                // Get YPlacement from next Base character
                $nextbase = $i + 1;
                while ($OTLdata['group']{$nextbase} != 'C') {
                    $nextbase++;
                }
                if (isset($GPOSinfo[$nextbase]) && isset($GPOSinfo[$nextbase]['YPlacement']) && $GPOSinfo[$nextbase]['YPlacement']) {
                    $YPlacement = $GPOSinfo[$nextbase]['YPlacement'] * $this->FontSizePt / $this->CurrentFont['unitsPerEm'];
                }

                // Prepare Text and Select Font ID
                if ($sipset) {
                    for ($j = 0; $j < 99; $j++) {
                        $init = array_search($c, $this->CurrentFont['subsets'][$j]);
                        if ($init !== false) {
                            if ($this->CurrentFont['subsetfontids'][$j] != $last_fontid) {
                                $fontid = $this->CurrentFont['subsetfontids'][$j];
                            }
                            $tx = sprintf("%02s", strtoupper(dechex($init)));
                            break;
                        } elseif (count($this->CurrentFont['subsets'][$j]) < 255) {
                            $n = count($this->CurrentFont['subsets'][$j]);
                            $this->CurrentFont['subsets'][$j][$n] = $c;
                            if ($this->CurrentFont['subsetfontids'][$j] != $last_fontid) {
                                $fontid = $this->CurrentFont['subsetfontids'][$j];
                            }
                            $tx = sprintf("%02s", strtoupper(dechex($n)));
                            break;
                        } elseif (!isset($this->CurrentFont['subsets'][($j + 1)])) {
                            $this->CurrentFont['subsets'][($j + 1)] = [0 => 0];
                            $this->CurrentFont['subsetfontids'][($j + 1)] = count($this->fonts) + $this->extraFontSubsets + 1;
                            $this->extraFontSubsets++;
                        }
                    }
                } else {
                    $tx = UtfString::code2utf($c);
                    $tx = $this->UTF8ToUTF16BE($tx, false);
                    $tx = $this->_escape($tx);
                }

                if ($kashida > $tatw) {
                    // Insert multiple tatweel characters, repositioning the last one to give correct total length
                    $fontstretch = 100;
                    $nt = intval($kashida / $tatw);
                    $nudgeback = (($nt + 1) * $tatw) - $kashida;
                    $optx = str_repeat($tx, $nt);
                    if ($sipset) {
                        $optx .= sprintf('>%d<', ($nudgeback));
                    } else {
                        $optx .= sprintf(')%d(', ($nudgeback));
                    }
                    $optx .= $tx; // #last
                } else {
                    // Insert single tatweel character and use fontstretch to get correct length
                    $fontstretch = ($kashida / $tatw) * 100;
                    $optx = $tx;
                }

                if ($sipset) {
                    $tj .= '>] TJ ';
                } else {
                    $tj .= ')] TJ ';
                }
                if ($fontid != $last_fontid || $fontsize != $last_fontsize) {
                    $tj .= sprintf(' /F%d %.3F Tf ', $fontid, $fontsize);
                }
                if ($fontstretch != $last_fontstretch) {
                    $tj .= sprintf('%d Tz ', $fontstretch);
                }
                $tj .= sprintf('%.3F Ts ', $YPlacement);
                if ($sipset) {
                    $tj .= '[<';
                } else {
                    $tj .= '[(';
                }

                // Output the code for the txt character(s)
                $tj .= $optx;
                $last_fontid = $fontid;
                $last_fontstretch = $fontstretch;
                $fontstretch = 100;
            }

            $lastYPlacement = $YPlacement;
        }


        // Finish up
        if ($sipset) {
            $tj .= '>';
            if ($XshiftAfter) {
                $tj .= sprintf('%d', (-$XshiftAfter));
            }
            if ($last_fontid != $original_fontid) {
                $tj .= '] TJ ';
                $tj .= sprintf(' /F%d %.3F Tf ', $original_fontid, $fontsize);
                $tj .= '[';
            }
            $tj = preg_replace('/([^\\\])<>/', '\\1 ', $tj);
        } else {
            $tj .= ')';
            if ($XshiftAfter) {
                $tj .= sprintf('%d', (-$XshiftAfter));
            }
            if ($last_fontid != $original_fontid) {
                $tj .= '] TJ ';
                $tj .= sprintf(' /F%d %.3F Tf ', $original_fontid, $fontsize);
                $tj .= '[';
            }
            $tj = preg_replace('/([^\\\])\(\)/', '\\1 ', $tj);
        }

        $s = sprintf(' BT ' . $aix . ' 0 Tc 0 Tw [%s] TJ ET ', $x, $y, $tj);

        // echo $s."\n\n"; // exit;

        return $s;
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = '', $fill = 0, $link = '', $directionality = 'ltr', $encoded = false, $OTLdata = false, $maxrows = false)
    {
        // maxrows is called from mpdfform->TEXTAREA
        // Parameter (pre-)encoded - When called internally from form::textarea - mb_encoding already done and OTL - but not reverse RTL
        if (!$encoded) {
            $txt = $this->purify_utf8_text($txt);
            if ($this->text_input_as_HTML) {
                $txt = $this->all_entities_to_utf8($txt);
            }
            if ($this->usingCoreFont) {
                $txt = mb_convert_encoding($txt, $this->mb_enc, 'UTF-8');
            }
            if (preg_match("/([" . $this->pregRTLchars . "])/u", $txt)) {
                $this->biDirectional = true;
            } // *OTL*
            /* -- OTL -- */
            $OTLdata = [];
            // Use OTL OpenType Table Layout - GSUB & GPOS
            if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                $txt = $this->otl->applyOTL($txt, $this->CurrentFont['useOTL']);
                $OTLdata = $this->otl->OTLdata;
            }
            if ($directionality == 'rtl' || $this->biDirectional) {
                if (!isset($OTLdata)) {
                    $unicode = $this->UTF8StringToArray($txt, false);
                    $is_strong = false;
                    $this->getBasicOTLdata($OTLdata, $unicode, $is_strong);
                }
            }
            /* -- END OTL -- */
        }
        if (!$align) {
            $align = $this->defaultAlign;
        }

        // Output text with automatic or explicit line breaks
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - ($this->cMarginL + $this->cMarginR));
        if ($this->usingCoreFont) {
            $s = str_replace("\r", '', $txt);
            $nb = strlen($s);
            while ($nb > 0 and $s[$nb - 1] == "\n") {
                $nb--;
            }
        } else {
            $s = str_replace("\r", '', $txt);
            $nb = mb_strlen($s, $this->mb_enc);
            while ($nb > 0 and mb_substr($s, $nb - 1, 1, $this->mb_enc) == "\n") {
                $nb--;
            }
        }
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (is_int(strpos($border, 'L'))) {
                    $b2.='L';
                }
                if (is_int(strpos($border, 'R'))) {
                    $b2.='R';
                }
                $b = is_int(strpos($border, 'T')) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;

        $rows = 0;
        $start_y = $this->y;

        if (!$this->usingCoreFont) {
            $inclCursive = false;
            if (preg_match("/([" . $this->pregCURSchars . "])/u", $s)) {
                $inclCursive = true;
            }
            while ($i < $nb) {
                // Get next character
                $c = mb_substr($s, $i, 1, $this->mb_enc);
                if ($c == "\n") {
                    // Explicit line break
                    // WORD SPACING
                    $this->ResetSpacing();
                    $tmp = rtrim(mb_substr($s, $j, $i - $j, $this->mb_enc));
                    $tmpOTLdata = false;
                    /* -- OTL -- */
                    if (isset($OTLdata)) {
                        $tmpOTLdata = $this->otl->sliceOTLdata($OTLdata, $j, $i - $j);
                        $this->otl->trimOTLdata($tmpOTLdata, false, true);
                        $this->magic_reverse_dir($tmp, $directionality, $tmpOTLdata);
                    }
                    /* -- END OTL -- */
                    $this->Cell($w, $h, $tmp, $b, 2, $align, $fill, $link, 0, 0, 0, 'M', 0, false, $tmpOTLdata);
                    if ($maxrows != false && isset($this->form) && ($this->y - $start_y) / $h > $maxrows) {
                        return false;
                    }
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border and $nl == 2) {
                        $b = $b2;
                    }
                    continue;
                }
                if ($c == " ") {
                    $sep = $i;
                    $ls = $l;
                    $ns++;
                }

                $l += $this->GetCharWidthNonCore($c);

                if ($l > $wmax) {
                    // Automatic line break
                    if ($sep == -1) { // Only one word
                        if ($i == $j) {
                            $i++;
                        }
                        // WORD SPACING
                        $this->ResetSpacing();
                        $tmp = rtrim(mb_substr($s, $j, $i - $j, $this->mb_enc));
                        $tmpOTLdata = false;
                        /* -- OTL -- */
                        if (isset($OTLdata)) {
                            $tmpOTLdata = $this->otl->sliceOTLdata($OTLdata, $j, $i - $j);
                            $this->otl->trimOTLdata($tmpOTLdata, false, true);
                            $this->magic_reverse_dir($tmp, $directionality, $tmpOTLdata);
                        }
                        /* -- END OTL -- */
                        $this->Cell($w, $h, $tmp, $b, 2, $align, $fill, $link, 0, 0, 0, 'M', 0, false, $tmpOTLdata);
                    } else {
                        $tmp = rtrim(mb_substr($s, $j, $sep - $j, $this->mb_enc));
                        $tmpOTLdata = false;
                        /* -- OTL -- */
                        if (isset($OTLdata)) {
                            $tmpOTLdata = $this->otl->sliceOTLdata($OTLdata, $j, $sep - $j);
                            $this->otl->trimOTLdata($tmpOTLdata, false, true);
                        }
                        /* -- END OTL -- */
                        if ($align == 'J') {
                            //////////////////////////////////////////
                            // JUSTIFY J using Unicode fonts (Word spacing doesn't work)
                            // WORD SPACING UNICODE
                            // Change NON_BREAKING SPACE to spaces so they are 'spaced' properly
                            $tmp = str_replace(chr(194) . chr(160), chr(32), $tmp);
                            $len_ligne = $this->GetStringWidth($tmp, false, $tmpOTLdata);
                            $nb_carac = mb_strlen($tmp, $this->mb_enc);
                            $nb_spaces = mb_substr_count($tmp, ' ', $this->mb_enc);
                            // Take off number of Marks
                            // Use GPOS OTL
                            if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'])) {
                                if (isset($tmpOTLdata['group']) && $tmpOTLdata['group']) {
                                    $nb_carac -= substr_count($tmpOTLdata['group'], 'M');
                                }
                            }

                            list($charspacing, $ws, $kashida) = $this->GetJspacing($nb_carac, $nb_spaces, ((($wmax) - $len_ligne) * Mpdf::SCALE), $inclCursive, $tmpOTLdata);
                            $this->SetSpacing($charspacing, $ws);
                            //////////////////////////////////////////
                        }
                        if (isset($OTLdata)) {
                            $this->magic_reverse_dir($tmp, $directionality, $tmpOTLdata);
                        }
                        $this->Cell($w, $h, $tmp, $b, 2, $align, $fill, $link, 0, 0, 0, 'M', 0, false, $tmpOTLdata);
                        $i = $sep + 1;
                    }
                    if ($maxrows != false && isset($this->form) && ($this->y - $start_y) / $h > $maxrows) {
                        return false;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border and $nl == 2) {
                        $b = $b2;
                    }
                } else {
                    $i++;
                }
            }
            // Last chunk
            // WORD SPACING

            $this->ResetSpacing();
        } else {
            while ($i < $nb) {
                // Get next character
                $c = $s[$i];
                if ($c == "\n") {
                    // Explicit line break
                    // WORD SPACING
                    $this->ResetSpacing();
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill, $link);
                    if ($maxrows != false && isset($this->form) && ($this->y - $start_y) / $h > $maxrows) {
                        return false;
                    }
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border and $nl == 2) {
                        $b = $b2;
                    }
                    continue;
                }
                if ($c == " ") {
                    $sep = $i;
                    $ls = $l;
                    $ns++;
                }

                $l += $this->GetCharWidthCore($c);
                if ($l > $wmax) {
                    // Automatic line break
                    if ($sep == -1) {
                        if ($i == $j) {
                            $i++;
                        }
                        // WORD SPACING
                        $this->ResetSpacing();
                        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill, $link);
                    } else {
                        if ($align == 'J') {
                            $tmp = rtrim(substr($s, $j, $sep - $j));
                            //////////////////////////////////////////
                            // JUSTIFY J using Unicode fonts (Word spacing doesn't work)
                            // WORD SPACING NON_UNICODE/CJK
                            // Change NON_BREAKING SPACE to spaces so they are 'spaced' properly
                            $tmp = str_replace(chr(160), chr(32), $tmp);
                            $len_ligne = $this->GetStringWidth($tmp);
                            $nb_carac = strlen($tmp);
                            $nb_spaces = substr_count($tmp, ' ');
                            $tmpOTLdata = [];
                            list($charspacing, $ws, $kashida) = $this->GetJspacing($nb_carac, $nb_spaces, ((($wmax) - $len_ligne) * Mpdf::SCALE), false, $tmpOTLdata);
                            $this->SetSpacing($charspacing, $ws);
                            //////////////////////////////////////////
                        }
                        $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill, $link);
                        $i = $sep + 1;
                    }
                    if ($maxrows != false && isset($this->form) && ($this->y - $start_y) / $h > $maxrows) {
                        return false;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    $ns = 0;
                    $nl++;
                    if ($border and $nl == 2) {
                        $b = $b2;
                    }
                } else {
                    $i++;
                }
            }
            // Last chunk
            // WORD SPACING

            $this->ResetSpacing();
        }
        // Last chunk
        if ($border and is_int(strpos($border, 'B'))) {
            $b.='B';
        }
        if (!$this->usingCoreFont) {
            $tmp = rtrim(mb_substr($s, $j, $i - $j, $this->mb_enc));
            $tmpOTLdata = false;
            /* -- OTL -- */
            if (isset($OTLdata)) {
                $tmpOTLdata = $this->otl->sliceOTLdata($OTLdata, $j, $i - $j);
                $this->otl->trimOTLdata($tmpOTLdata, false, true);
                $this->magic_reverse_dir($tmp, $directionality, $tmpOTLdata);
            }
            /* -- END OTL -- */
            $this->Cell($w, $h, $tmp, $b, 2, $align, $fill, $link, 0, 0, 0, 'M', 0, false, $tmpOTLdata);
        } else {
            $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill, $link);
        }
        $this->x = $this->lMargin;
    }

    /* -- DIRECTW -- */

    public function Write($h, $txt, $currentx = 0, $link = '', $directionality = 'ltr', $align = '', $fill = 0)
    {
        if (empty($this->directWrite)) {
            $this->directWrite = new DirectWrite($this, $this->otl, $this->sizeConverter, $this->colorConverter);
        }

        $this->directWrite->Write($h, $txt, $currentx, $link, $directionality, $align, $fill);
    }

    /* -- END DIRECTW -- */


    /* -- HTML-CSS -- */

    public function saveInlineProperties()
    {
        $saved = [];
        $saved['family'] = $this->FontFamily;
        $saved['style'] = $this->FontStyle;
        $saved['sizePt'] = $this->FontSizePt;
        $saved['size'] = $this->FontSize;
        $saved['HREF'] = $this->HREF;
        $saved['textvar'] = $this->textvar; // mPDF 5.7.1
        $saved['OTLtags'] = $this->OTLtags; // mPDF 5.7.1
        $saved['textshadow'] = $this->textshadow;
        $saved['linewidth'] = $this->LineWidth;
        $saved['drawcolor'] = $this->DrawColor;
        $saved['textparam'] = $this->textparam;
        $saved['lSpacingCSS'] = $this->lSpacingCSS;
        $saved['wSpacingCSS'] = $this->wSpacingCSS;
        $saved['I'] = $this->I;
        $saved['B'] = $this->B;
        $saved['colorarray'] = $this->colorarray;
        $saved['bgcolorarray'] = $this->spanbgcolorarray;
        $saved['border'] = $this->spanborddet;
        $saved['color'] = $this->TextColor;
        $saved['bgcolor'] = $this->FillColor;
        $saved['lang'] = $this->currentLang;
        $saved['fontLanguageOverride'] = $this->fontLanguageOverride; // mPDF 5.7.1
        $saved['display_off'] = $this->inlineDisplayOff;

        return $saved;
    }

    public function restoreInlineProperties(&$saved)
    {
        $FontFamily = $saved['family'];
        $this->FontStyle = $saved['style'];
        $this->FontSizePt = $saved['sizePt'];
        $this->FontSize = $saved['size'];

        $this->currentLang = $saved['lang'];
        $this->fontLanguageOverride = $saved['fontLanguageOverride']; // mPDF 5.7.1

        $this->ColorFlag = ($this->FillColor != $this->TextColor); // Restore ColorFlag as well

        $this->HREF = $saved['HREF'];
        $this->textvar = $saved['textvar']; // mPDF 5.7.1
        $this->OTLtags = $saved['OTLtags']; // mPDF 5.7.1
        $this->textshadow = $saved['textshadow'];
        $this->LineWidth = $saved['linewidth'];
        $this->DrawColor = $saved['drawcolor'];
        $this->textparam = $saved['textparam'];
        $this->inlineDisplayOff = $saved['display_off'];

        $this->lSpacingCSS = $saved['lSpacingCSS'];
        if (($this->lSpacingCSS || $this->lSpacingCSS === '0') && strtoupper($this->lSpacingCSS) != 'NORMAL') {
            $this->fixedlSpacing = $this->sizeConverter->convert($this->lSpacingCSS, $this->FontSize);
        } else {
            $this->fixedlSpacing = false;
        }
        $this->wSpacingCSS = $saved['wSpacingCSS'];
        if ($this->wSpacingCSS && strtoupper($this->wSpacingCSS) != 'NORMAL') {
            $this->minwSpacing = $this->sizeConverter->convert($this->wSpacingCSS, $this->FontSize);
        } else {
            $this->minwSpacing = 0;
        }

        $this->SetFont($FontFamily, $saved['style'], $saved['sizePt'], false);

        $this->currentfontstyle = $saved['style'];
        $this->currentfontsize = $saved['sizePt'];
        $this->SetStylesArray(['B' => $saved['B'], 'I' => $saved['I']]); // mPDF 5.7.1

        $this->TextColor = $saved['color'];
        $this->FillColor = $saved['bgcolor'];
        $this->colorarray = $saved['colorarray'];
        $cor = $saved['colorarray'];
        if ($cor) {
            $this->SetTColor($cor);
        }
        $this->spanbgcolorarray = $saved['bgcolorarray'];
        $cor = $saved['bgcolorarray'];
        if ($cor) {
            $this->SetFColor($cor);
        }
        $this->spanborddet = $saved['border'];
    }

    // Used when ColActive for tables - updated to return first block with background fill OR borders
    public function GetFirstBlockFill()
    {
        // Returns the first blocklevel that uses a bgcolor fill
        $startfill = 0;
        for ($i = 1; $i <= $this->blklvl; $i++) {
            if ($this->blk[$i]['bgcolor'] || $this->blk[$i]['border_left']['w'] || $this->blk[$i]['border_right']['w'] || $this->blk[$i]['border_top']['w'] || $this->blk[$i]['border_bottom']['w']) {
                $startfill = $i;
                break;
            }
        }
        return $startfill;
    }

    // -------------------------FLOWING BLOCK------------------------------------//
    // The following functions were originally written by Damon Kohler           //
    // --------------------------------------------------------------------------//

    public function saveFont()
    {
        $saved = [];
        $saved['family'] = $this->FontFamily;
        $saved['style'] = $this->FontStyle;
        $saved['sizePt'] = $this->FontSizePt;
        $saved['size'] = $this->FontSize;
        $saved['curr'] = &$this->CurrentFont;
        $saved['lang'] = $this->currentLang; // mPDF 6
        $saved['color'] = $this->TextColor;
        $saved['spanbgcolor'] = $this->spanbgcolor;
        $saved['spanbgcolorarray'] = $this->spanbgcolorarray;
        $saved['bord'] = $this->spanborder;
        $saved['border'] = $this->spanborddet;
        $saved['HREF'] = $this->HREF;
        $saved['textvar'] = $this->textvar; // mPDF 5.7.1
        $saved['textshadow'] = $this->textshadow;
        $saved['linewidth'] = $this->LineWidth;
        $saved['drawcolor'] = $this->DrawColor;
        $saved['textparam'] = $this->textparam;
        $saved['ReqFontStyle'] = $this->ReqFontStyle;
        $saved['fixedlSpacing'] = $this->fixedlSpacing;
        $saved['minwSpacing'] = $this->minwSpacing;
        return $saved;
    }

    public function restoreFont(&$saved, $write = true)
    {
        if (!isset($saved) || empty($saved)) {
            return;
        }

        $this->FontFamily = $saved['family'];
        $this->FontStyle = $saved['style'];
        $this->FontSizePt = $saved['sizePt'];
        $this->FontSize = $saved['size'];
        $this->CurrentFont = &$saved['curr'];
        $this->currentLang = $saved['lang']; // mPDF 6
        $this->TextColor = $saved['color'];
        $this->spanbgcolor = $saved['spanbgcolor'];
        $this->spanbgcolorarray = $saved['spanbgcolorarray'];
        $this->spanborder = $saved['bord'];
        $this->spanborddet = $saved['border'];
        $this->ColorFlag = ($this->FillColor != $this->TextColor); // Restore ColorFlag as well
        $this->HREF = $saved['HREF'];
        $this->fixedlSpacing = $saved['fixedlSpacing'];
        $this->minwSpacing = $saved['minwSpacing'];
        $this->textvar = $saved['textvar'];  // mPDF 5.7.1
        $this->textshadow = $saved['textshadow'];
        $this->LineWidth = $saved['linewidth'];
        $this->DrawColor = $saved['drawcolor'];
        $this->textparam = $saved['textparam'];
        if ($write) {
            $this->SetFont($saved['family'], $saved['style'], $saved['sizePt'], true, true); // force output
            $fontout = (sprintf('BT /F%d %.3F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
            if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['Font']) && $this->pageoutput[$this->page]['Font'] != $fontout) || !isset($this->pageoutput[$this->page]['Font']))) {
                $this->_out($fontout);
            }
            $this->pageoutput[$this->page]['Font'] = $fontout;
        } else {
            $this->SetFont($saved['family'], $saved['style'], $saved['sizePt'], false);
        }
        $this->ReqFontStyle = $saved['ReqFontStyle'];
    }

    public function newFlowingBlock($w, $h, $a = '', $is_table = false, $blockstate = 0, $newblock = true, $blockdir = 'ltr', $table_draft = false)
    {
        if (!$a) {
            if ($blockdir == 'rtl') {
                $a = 'R';
            } else {
                $a = 'L';
            }
        }
        $this->flowingBlockAttr['width'] = ($w * Mpdf::SCALE);
        // line height in user units
        $this->flowingBlockAttr['is_table'] = $is_table;
        $this->flowingBlockAttr['table_draft'] = $table_draft;
        $this->flowingBlockAttr['height'] = $h;
        $this->flowingBlockAttr['lineCount'] = 0;
        $this->flowingBlockAttr['align'] = $a;
        $this->flowingBlockAttr['font'] = [];
        $this->flowingBlockAttr['content'] = [];
        $this->flowingBlockAttr['contentB'] = [];
        $this->flowingBlockAttr['contentWidth'] = 0;
        $this->flowingBlockAttr['blockstate'] = $blockstate;

        $this->flowingBlockAttr['newblock'] = $newblock;
        $this->flowingBlockAttr['valign'] = 'M';
        $this->flowingBlockAttr['blockdir'] = $blockdir;
        $this->flowingBlockAttr['cOTLdata'] = []; // mPDF 5.7.1
        $this->flowingBlockAttr['lastBidiText'] = ''; // mPDF 5.7.1
        if (!empty($this->otl)) {
            $this->otl->lastBidiStrongType = '';
        } // *OTL*
    }

    public function finishFlowingBlock($endofblock = false, $next = '')
    {
        $currentx = $this->x;
        // prints out the last chunk
        $is_table = $this->flowingBlockAttr['is_table'];
        $table_draft = $this->flowingBlockAttr['table_draft'];
        $maxWidth = & $this->flowingBlockAttr['width'];
        $stackHeight = & $this->flowingBlockAttr['height'];
        $align = & $this->flowingBlockAttr['align'];
        $content = & $this->flowingBlockAttr['content'];
        $contentB = & $this->flowingBlockAttr['contentB'];
        $font = & $this->flowingBlockAttr['font'];
        $contentWidth = & $this->flowingBlockAttr['contentWidth'];
        $lineCount = & $this->flowingBlockAttr['lineCount'];
        $valign = & $this->flowingBlockAttr['valign'];
        $blockstate = $this->flowingBlockAttr['blockstate'];

        $cOTLdata = & $this->flowingBlockAttr['cOTLdata']; // mPDF 5.7.1
        $newblock = $this->flowingBlockAttr['newblock'];
        $blockdir = $this->flowingBlockAttr['blockdir'];

        // *********** BLOCK BACKGROUND COLOR *****************//
        if ($this->blk[$this->blklvl]['bgcolor'] && !$is_table) {
            $fill = 0;
        } else {
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
            $fill = 0;
        }

        $hanger = '';
        // Always right trim!
        // Right trim last content and adjust width if needed to justify (later)
        if (isset($content[count($content) - 1]) && preg_match('/[ ]+$/', $content[count($content) - 1], $m)) {
            $strip = strlen($m[0]);
            $content[count($content) - 1] = substr($content[count($content) - 1], 0, (strlen($content[count($content) - 1]) - $strip));
            /* -- OTL -- */
            if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                $this->otl->trimOTLdata($cOTLdata[count($cOTLdata) - 1], false, true);
            }
            /* -- END OTL -- */
        }

        // the amount of space taken up so far in user units
        $usedWidth = 0;

        // COLS
        $oldcolumn = $this->CurrCol;

        if ($this->ColActive && !$is_table) {
            $this->breakpoints[$this->CurrCol][] = $this->y;
        } // *COLUMNS*
        // Print out each chunk

        /* -- TABLES -- */
        if ($is_table) {
            $ipaddingL = 0;
            $ipaddingR = 0;
            $paddingL = 0;
            $paddingR = 0;
        } else {
            /* -- END TABLES -- */
            $ipaddingL = $this->blk[$this->blklvl]['padding_left'];
            $ipaddingR = $this->blk[$this->blklvl]['padding_right'];
            $paddingL = ($ipaddingL * Mpdf::SCALE);
            $paddingR = ($ipaddingR * Mpdf::SCALE);
            $this->cMarginL = $this->blk[$this->blklvl]['border_left']['w'];
            $this->cMarginR = $this->blk[$this->blklvl]['border_right']['w'];

            // Added mPDF 3.0 Float DIV
            $fpaddingR = 0;
            $fpaddingL = 0;
            /* -- CSS-FLOAT -- */
            if (count($this->floatDivs)) {
                list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($this->blklvl);
                if ($r_exists) {
                    $fpaddingR = $r_width;
                }
                if ($l_exists) {
                    $fpaddingL = $l_width;
                }
            }
            /* -- END CSS-FLOAT -- */

            $usey = $this->y + 0.002;
            if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 0)) {
                $usey += $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
            }
            /* -- CSS-IMAGE-FLOAT -- */
            // If float exists at this level
            if (isset($this->floatmargins['R']) && $usey <= $this->floatmargins['R']['y1'] && $usey >= $this->floatmargins['R']['y0'] && !$this->floatmargins['R']['skipline']) {
                $fpaddingR += $this->floatmargins['R']['w'];
            }
            if (isset($this->floatmargins['L']) && $usey <= $this->floatmargins['L']['y1'] && $usey >= $this->floatmargins['L']['y0'] && !$this->floatmargins['L']['skipline']) {
                $fpaddingL += $this->floatmargins['L']['w'];
            }
            /* -- END CSS-IMAGE-FLOAT -- */
        } // *TABLES*


        $lineBox = [];

        $this->_setInlineBlockHeights($lineBox, $stackHeight, $content, $font, $is_table);

        if ($is_table && count($content) == 0) {
            $stackHeight = 0;
        }

        if ($table_draft) {
            $this->y += $stackHeight;
            $this->objectbuffer = [];
            return 0;
        }

        // While we're at it, check if contains cursive text
        // Change NBSP to SPACE.
        // Re-calculate contentWidth
        $contentWidth = 0;

        foreach ($content as $k => $chunk) {
            $this->restoreFont($font[$k], false);
            if (!isset($this->objectbuffer[$k]) || (isset($this->objectbuffer[$k]) && !$this->objectbuffer[$k])) {
                // Soft Hyphens chr(173)
                if (!$this->usingCoreFont) {
                    /* -- OTL -- */
                    // mPDF 5.7.1
                    if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                        $this->otl->removeChar($chunk, $cOTLdata[$k], "\xc2\xad");
                        $this->otl->replaceSpace($chunk, $cOTLdata[$k]);
                        $content[$k] = $chunk;
                    } /* -- END OTL -- */ else {  // *OTL*
                        $content[$k] = $chunk = str_replace("\xc2\xad", '', $chunk);
                        $content[$k] = $chunk = str_replace(chr(194) . chr(160), chr(32), $chunk);
                    } // *OTL*
                } elseif ($this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats') {
                    $content[$k] = $chunk = str_replace(chr(173), '', $chunk);
                    $content[$k] = $chunk = str_replace(chr(160), chr(32), $chunk);
                }
                $contentWidth += $this->GetStringWidth($chunk, true, (isset($cOTLdata[$k]) ? $cOTLdata[$k] : false), $this->textvar) * Mpdf::SCALE;
            } elseif (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]) {
                // LIST MARKERS // mPDF 6  Lists
                if ($this->objectbuffer[$k]['type'] == 'image' && isset($this->objectbuffer[$k]['listmarker']) && $this->objectbuffer[$k]['listmarker'] && $this->objectbuffer[$k]['listmarkerposition'] == 'outside') {
                    // do nothing
                } else {
                    $contentWidth += $this->objectbuffer[$k]['OUTER-WIDTH'] * Mpdf::SCALE;
                }
            }
        }

        if (isset($font[count($font) - 1])) {
            $lastfontreqstyle = (isset($font[count($font) - 1]['ReqFontStyle']) ? $font[count($font) - 1]['ReqFontStyle'] : '');
            $lastfontstyle = (isset($font[count($font) - 1]['style']) ? $font[count($font) - 1]['style'] : '');
        } else {
            $lastfontreqstyle = null;
            $lastfontstyle = null;
        }
        if ($blockdir == 'ltr' && strpos($lastfontreqstyle, "I") !== false && strpos($lastfontstyle, "I") === false) { // Artificial italic
            $lastitalic = $this->FontSize * 0.15 * Mpdf::SCALE;
        } else {
            $lastitalic = 0;
        }

        // Get PAGEBREAK TO TEST for height including the bottom border/padding
        $check_h = max($this->divheight, $stackHeight);

        // This fixes a proven bug...
        if ($endofblock && $newblock && $blockstate == 0 && !$content) {
            $check_h = 0;
        }
        // but ? needs to fix potentially more widespread...
        // if (!$content) {  $check_h = 0; }

        if ($this->blklvl > 0 && !$is_table) {
            if ($endofblock && $blockstate > 1) {
                if ($this->blk[$this->blklvl]['page_break_after_avoid']) {
                    $check_h += $stackHeight;
                }
                $check_h += ($this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w']);
            }
            if (($newblock && ($blockstate == 1 || $blockstate == 3) && $lineCount == 0) || ($endofblock && $blockstate == 3 && $lineCount == 0)) {
                $check_h += ($this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['border_top']['w']);
            }
        }

        // Force PAGE break if column height cannot take check-height
        if ($this->ColActive && $check_h > ($this->PageBreakTrigger - $this->y0)) {
            $this->SetCol($this->NbCol - 1);
        }

        // Avoid just border/background-color moved on to next page
        if ($endofblock && $blockstate > 1 && !$content) {
            $buff = $this->margBuffer;
        } else {
            $buff = 0;
        }


        // PAGEBREAK
        if (!$is_table && ($this->y + $check_h) > ($this->PageBreakTrigger + $buff) and ! $this->InFooter and $this->AcceptPageBreak()) {
            $bak_x = $this->x; // Current X position
            // WORD SPACING
            $ws = $this->ws; // Word Spacing
            $charspacing = $this->charspacing; // Character Spacing
            $this->ResetSpacing();

            $this->AddPage($this->CurOrientation);

            $this->x = $bak_x;
            // Added to correct for OddEven Margins
            $currentx += $this->MarginCorrection;
            $this->x += $this->MarginCorrection;

            // WORD SPACING
            $this->SetSpacing($charspacing, $ws);
        }


        /* -- COLUMNS -- */
        // COLS
        // COLUMN CHANGE
        if ($this->CurrCol != $oldcolumn) {
            $currentx += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
            $this->x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
            $oldcolumn = $this->CurrCol;
        }


        if ($this->ColActive && !$is_table) {
            $this->breakpoints[$this->CurrCol][] = $this->y;
        }
        /* -- END COLUMNS -- */

        // TOP MARGIN
        if ($newblock && ($blockstate == 1 || $blockstate == 3) && ($this->blk[$this->blklvl]['margin_top']) && $lineCount == 0 && !$is_table) {
            $this->DivLn($this->blk[$this->blklvl]['margin_top'], $this->blklvl - 1, true, $this->blk[$this->blklvl]['margin_collapse']);
            if ($this->ColActive) {
                $this->breakpoints[$this->CurrCol][] = $this->y;
            } // *COLUMNS*
        }

        if ($newblock && ($blockstate == 1 || $blockstate == 3) && $lineCount == 0 && !$is_table) {
            $this->blk[$this->blklvl]['y0'] = $this->y;
            $this->blk[$this->blklvl]['startpage'] = $this->page;
            if ($this->blk[$this->blklvl]['float']) {
                $this->blk[$this->blklvl]['float_start_y'] = $this->y;
            }
            if ($this->ColActive) {
                $this->breakpoints[$this->CurrCol][] = $this->y;
            } // *COLUMNS*
        }

        // Paragraph INDENT
        $WidthCorrection = 0;
        if (($newblock) && ($blockstate == 1 || $blockstate == 3) && isset($this->blk[$this->blklvl]['text_indent']) && ($lineCount == 0) && (!$is_table) && ($align != 'C')) {
            $ti = $this->sizeConverter->convert($this->blk[$this->blklvl]['text_indent'], $this->blk[$this->blklvl]['inner_width'], $this->blk[$this->blklvl]['InlineProperties']['size'], false);  // mPDF 5.7.4
            $WidthCorrection = ($ti * Mpdf::SCALE);
        }


        // PADDING and BORDER spacing/fill
        if (($newblock) && ($blockstate == 1 || $blockstate == 3) && (($this->blk[$this->blklvl]['padding_top']) || ($this->blk[$this->blklvl]['border_top'])) && ($lineCount == 0) && (!$is_table)) {
            // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
            $this->DivLn($this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'], -3, true, false, 1);
            if ($this->ColActive) {
                $this->breakpoints[$this->CurrCol][] = $this->y;
            } // *COLUMNS*
            $this->x = $currentx;
        }


        // Added mPDF 3.0 Float DIV
        $fpaddingR = 0;
        $fpaddingL = 0;
        /* -- CSS-FLOAT -- */
        if (count($this->floatDivs)) {
            list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($this->blklvl);
            if ($r_exists) {
                $fpaddingR = $r_width;
            }
            if ($l_exists) {
                $fpaddingL = $l_width;
            }
        }
        /* -- END CSS-FLOAT -- */

        $usey = $this->y + 0.002;
        if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 0)) {
            $usey += $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
        }
        /* -- CSS-IMAGE-FLOAT -- */
        // If float exists at this level
        if (isset($this->floatmargins['R']) && $usey <= $this->floatmargins['R']['y1'] && $usey >= $this->floatmargins['R']['y0'] && !$this->floatmargins['R']['skipline']) {
            $fpaddingR += $this->floatmargins['R']['w'];
        }
        if (isset($this->floatmargins['L']) && $usey <= $this->floatmargins['L']['y1'] && $usey >= $this->floatmargins['L']['y0'] && !$this->floatmargins['L']['skipline']) {
            $fpaddingL += $this->floatmargins['L']['w'];
        }
        /* -- END CSS-IMAGE-FLOAT -- */


        if ($content) {
            // In FinishFlowing Block no lines are justified as it is always last line
            // but if CJKorphan has allowed content width to go over max width, use J charspacing to compress line
            // JUSTIFICATION J - NOT!
            $nb_carac = 0;
            $nb_spaces = 0;
            $jcharspacing = 0;
            $jkashida = 0;
            $jws = 0;
            $inclCursive = false;
            $dottab = false;
            foreach ($content as $k => $chunk) {
                if (!isset($this->objectbuffer[$k]) || (isset($this->objectbuffer[$k]) && !$this->objectbuffer[$k])) {
                    $nb_carac += mb_strlen($chunk, $this->mb_enc);
                    $nb_spaces += mb_substr_count($chunk, ' ', $this->mb_enc);
                    // mPDF 6
                    // Use GPOS OTL
                    $this->restoreFont($font[$k], false);
                    if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                        if (isset($cOTLdata[$k]['group']) && $cOTLdata[$k]['group']) {
                            $nb_marks = substr_count($cOTLdata[$k]['group'], 'M');
                            $nb_carac -= $nb_marks;
                        }
                        if (preg_match("/([" . $this->pregCURSchars . "])/u", $chunk)) {
                            $inclCursive = true;
                        }
                    }
                } else {
                    $nb_carac ++;  // mPDF 6 allow spacing for inline object
                    if ($this->objectbuffer[$k]['type'] == 'dottab') {
                        $dottab = $this->objectbuffer[$k]['outdent'];
                    }
                }
            }

            // DIRECTIONALITY RTL
            $chunkorder = range(0, count($content) - 1); // mPDF 6
            /* -- OTL -- */
            // mPDF 6
            if ($blockdir == 'rtl' || $this->biDirectional) {
                $this->otl->bidiReorder($chunkorder, $content, $cOTLdata, $blockdir);
                // From this point on, $content and $cOTLdata may contain more elements (and re-ordered) compared to
                // $this->objectbuffer and $font ($chunkorder contains the mapping)
            }
            /* -- END OTL -- */

            // Remove any XAdvance from OTL data at end of line
            // And correct for XPlacement on last character
            // BIDI is applied
            foreach ($chunkorder as $aord => $k) {
                if (count($cOTLdata)) {
                    $this->restoreFont($font[$k], false);
                    // ...FinishFlowingBlock...
                    if ($aord == count($chunkorder) - 1 && isset($cOTLdata[$aord]['group'])) { // Last chunk on line
                        $nGPOS = strlen($cOTLdata[$aord]['group']) - 1; // Last character
                        if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL']) || isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'])) {
                            if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'])) {
                                $w = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] * 1000 / $this->CurrentFont['unitsPerEm'];
                            } else {
                                $w = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] * 1000 / $this->CurrentFont['unitsPerEm'];
                            }
                            $w *= ($this->FontSize / 1000);
                            $contentWidth -= $w * Mpdf::SCALE;
                            $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] = 0;
                            $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] = 0;
                        }

                        // If last character has an XPlacement set, adjust width calculation, and add to XAdvance to account for it
                        if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'])) {
                            $w = -$cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'] * 1000 / $this->CurrentFont['unitsPerEm'];
                            $w *= ($this->FontSize / 1000);
                            $contentWidth -= $w * Mpdf::SCALE;
                            $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'];
                            $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'];
                        }
                    }
                }
            }

            // if it's justified, we need to find the char/word spacing (or if orphans have allowed length of line to go over the maxwidth)
            // If "orphans" in fact is just a final space - ignore this
            $lastchar = mb_substr($content[(count($chunkorder) - 1)], mb_strlen($content[(count($chunkorder) - 1)], $this->mb_enc) - 1, 1, $this->mb_enc);
            if (preg_match("/[" . $this->CJKoverflow . "]/u", $lastchar)) {
                $CJKoverflow = true;
            } else {
                $CJKoverflow = false;
            }
            if ((((($contentWidth + $lastitalic) > $maxWidth) && ($content[(count($chunkorder) - 1)] != ' ') ) ||
                (!$endofblock && $align == 'J' && ($next == 'image' || $next == 'select' || $next == 'input' || $next == 'textarea' || ($next == 'br' && $this->justifyB4br)))) && !($CJKoverflow && $this->allowCJKoverflow)) {
                // WORD SPACING
                list($jcharspacing, $jws, $jkashida) = $this->GetJspacing($nb_carac, $nb_spaces, ($maxWidth - $lastitalic - $contentWidth - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) )), $inclCursive, $cOTLdata);
            } /* -- CJK-FONTS -- */ elseif ($this->checkCJK && $align == 'J' && $CJKoverflow && $this->allowCJKoverflow && $this->CJKforceend) {
                // force-end overhang
                $hanger = mb_substr($content[(count($chunkorder) - 1)], mb_strlen($content[(count($chunkorder) - 1)], $this->mb_enc) - 1, 1, $this->mb_enc);
                if (preg_match("/[" . $this->CJKoverflow . "]/u", $hanger)) {
                    $content[(count($chunkorder) - 1)] = mb_substr($content[(count($chunkorder) - 1)], 0, mb_strlen($content[(count($chunkorder) - 1)], $this->mb_enc) - 1, $this->mb_enc);
                    $this->restoreFont($font[$chunkorder[count($chunkorder) - 1]], false);
                    $contentWidth -= $this->GetStringWidth($hanger) * Mpdf::SCALE;
                    $nb_carac -= 1;
                    list($jcharspacing, $jws, $jkashida) = $this->GetJspacing($nb_carac, $nb_spaces, ($maxWidth - $lastitalic - $contentWidth - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) )), $inclCursive, $cOTLdata);
                }
            } /* -- END CJK-FONTS -- */

            // Check if will fit at word/char spacing of previous line - if so continue it
            // but only allow a maximum of $this->jSmaxWordLast and $this->jSmaxCharLast
            elseif ($contentWidth < ($maxWidth - $lastitalic - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE))) && !$this->fixedlSpacing) {
                if ($this->ws > $this->jSmaxWordLast) {
                    $jws = $this->jSmaxWordLast;
                }
                if ($this->charspacing > $this->jSmaxCharLast) {
                    $jcharspacing = $this->jSmaxCharLast;
                }
                $check = $maxWidth - $lastitalic - $WidthCorrection - $contentWidth - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) ) - ( $jcharspacing * $nb_carac) - ( $jws * $nb_spaces);
                if ($check <= 0) {
                    $jcharspacing = 0;
                    $jws = 0;
                }
            }

            $empty = $maxWidth - $lastitalic - $WidthCorrection - $contentWidth - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) );


            $empty -= ($jcharspacing * ($nb_carac - 1)); // mPDF 6 nb_carac MINUS 1
            $empty -= ($jws * $nb_spaces);
            $empty -= ($jkashida);

            $empty /= Mpdf::SCALE;

            if (!$is_table) {
                $this->maxPosR = max($this->maxPosR, ($this->w - $this->rMargin - $this->blk[$this->blklvl]['outer_right_margin'] - $empty));
                $this->maxPosL = min($this->maxPosL, ($this->lMargin + $this->blk[$this->blklvl]['outer_left_margin'] + $empty));
            }

            $arraysize = count($chunkorder);

            $margins = ($this->cMarginL + $this->cMarginR) + ($ipaddingL + $ipaddingR + $fpaddingR + $fpaddingR );

            if (!$is_table) {
                $this->DivLn($stackHeight, $this->blklvl, false);
            } // false -> don't advance y

            $this->x = $currentx + $this->cMarginL + $ipaddingL + $fpaddingL;
            if ($dottab !== false && $blockdir == 'rtl') {
                $this->x -= $dottab;
            } elseif ($align == 'R') {
                $this->x += $empty;
            } elseif ($align == 'J' && $blockdir == 'rtl') {
                $this->x += $empty;
            } elseif ($align == 'C') {
                $this->x += ($empty / 2);
            }

            // Paragraph INDENT
            $WidthCorrection = 0;
            if (($newblock) && ($blockstate == 1 || $blockstate == 3) && isset($this->blk[$this->blklvl]['text_indent']) && ($lineCount == 0) && (!$is_table) && ($align != 'C')) {
                $ti = $this->sizeConverter->convert($this->blk[$this->blklvl]['text_indent'], $this->blk[$this->blklvl]['inner_width'], $this->blk[$this->blklvl]['InlineProperties']['size'], false);  // mPDF 5.7.4
                if ($blockdir != 'rtl') {
                    $this->x += $ti;
                } // mPDF 6
            }

            foreach ($chunkorder as $aord => $k) { // mPDF 5.7
                $chunk = $content[$aord];
                if (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]) {
                    $xadj = $this->x - $this->objectbuffer[$k]['OUTER-X'];
                    $this->objectbuffer[$k]['OUTER-X'] += $xadj;
                    $this->objectbuffer[$k]['BORDER-X'] += $xadj;
                    $this->objectbuffer[$k]['INNER-X'] += $xadj;

                    if ($this->objectbuffer[$k]['type'] == 'listmarker') {
                        $this->objectbuffer[$k]['lineBox'] = $lineBox[-1]; // Block element details for glyph-origin
                    }
                    $yadj = $this->y - $this->objectbuffer[$k]['OUTER-Y'];
                    if ($this->objectbuffer[$k]['type'] == 'dottab') { // mPDF 6 DOTTAB
                        $this->objectbuffer[$k]['lineBox'] = $lineBox[$k]; // element details for glyph-origin
                    }
                    if ($this->objectbuffer[$k]['type'] != 'dottab') { // mPDF 6 DOTTAB
                        $yadj += $lineBox[$k]['top'];
                    }
                    $this->objectbuffer[$k]['OUTER-Y'] += $yadj;
                    $this->objectbuffer[$k]['BORDER-Y'] += $yadj;
                    $this->objectbuffer[$k]['INNER-Y'] += $yadj;
                }

                $this->restoreFont($font[$k]);  // mPDF 5.7

                if ($is_table && substr($align, 0, 1) == 'D' && $aord == 0) {
                    $dp = $this->decimal_align[substr($align, 0, 2)];
                    $s = preg_split('/' . preg_quote($dp, '/') . '/', $content[0], 2);  // ? needs to be /u if not core
                    $s0 = $this->GetStringWidth($s[0], false);
                    $this->x += ($this->decimal_offset - $s0);
                }

                $this->SetSpacing(($this->fixedlSpacing * Mpdf::SCALE) + $jcharspacing, ($this->fixedlSpacing + $this->minwSpacing) * Mpdf::SCALE + $jws);
                $this->fixedlSpacing = false;
                $this->minwSpacing = 0;

                $save_vis = $this->visibility;
                if (isset($this->textparam['visibility']) && $this->textparam['visibility'] && $this->textparam['visibility'] != $this->visibility) {
                    $this->SetVisibility($this->textparam['visibility']);
                }

                // *********** SPAN BACKGROUND COLOR ***************** //
                if (isset($this->spanbgcolor) && $this->spanbgcolor) {
                    $cor = $this->spanbgcolorarray;
                    $this->SetFColor($cor);
                    $save_fill = $fill;
                    $spanfill = 1;
                    $fill = 1;
                }
                if (!empty($this->spanborddet)) {
                    if (strpos($contentB[$k], 'L') !== false && isset($this->spanborddet['L'])) {
                        $this->x += $this->spanborddet['L']['w'];
                    }
                    if (strpos($contentB[$k], 'L') === false) {
                        $this->spanborddet['L']['s'] = $this->spanborddet['L']['w'] = 0;
                    }
                    if (strpos($contentB[$k], 'R') === false) {
                        $this->spanborddet['R']['s'] = $this->spanborddet['R']['w'] = 0;
                    }
                }
                // WORD SPACING
                // mPDF 5.7.1
                $stringWidth = $this->GetStringWidth($chunk, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar);
                $nch = mb_strlen($chunk, $this->mb_enc);
                // Use GPOS OTL
                if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                    if (isset($cOTLdata[$aord]['group']) && $cOTLdata[$aord]['group']) {
                        $nch -= substr_count($cOTLdata[$aord]['group'], 'M');
                    }
                }
                $stringWidth += ( $this->charspacing * $nch / Mpdf::SCALE );

                $stringWidth += ( $this->ws * mb_substr_count($chunk, ' ', $this->mb_enc) / Mpdf::SCALE );

                if (isset($this->objectbuffer[$k])) {
                    if ($this->objectbuffer[$k]['type'] == 'dottab') {
                        $this->objectbuffer[$k]['OUTER-WIDTH'] +=$empty;
                        $this->objectbuffer[$k]['OUTER-WIDTH'] +=$this->objectbuffer[$k]['outdent'];
                    }
                    // LIST MARKERS // mPDF 6  Lists
                    if ($this->objectbuffer[$k]['type'] == 'image' && isset($this->objectbuffer[$k]['listmarker']) && $this->objectbuffer[$k]['listmarker'] && $this->objectbuffer[$k]['listmarkerposition'] == 'outside') {
                        // do nothing
                    } else {
                        $stringWidth = $this->objectbuffer[$k]['OUTER-WIDTH'];
                    }
                }

                if ($stringWidth == 0) {
                    $stringWidth = 0.000001;
                }
                if ($aord == $arraysize - 1) { // mPDF 5.7
                    // mPDF 5.7.1
                    if ($this->checkCJK && $CJKoverflow && $align == 'J' && $this->allowCJKoverflow && $hanger && $this->CJKforceend) {
                        // force-end overhang
                        $this->Cell($stringWidth, $stackHeight, $chunk, '', 0, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false));  // mPDF 5.7.1
                        $this->Cell($this->GetStringWidth($hanger), $stackHeight, $hanger, '', 1, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false)); // mPDF 5.7.1
                    } else {
                        $this->Cell($stringWidth, $stackHeight, $chunk, '', 1, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false)); // mPDF 5.7.1
                    }
                } else {
                    $this->Cell($stringWidth, $stackHeight, $chunk, '', 0, '', $fill, $this->HREF, 0, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false)); // first or middle part  // mPDF 5.7.1
                }


                if (!empty($this->spanborddet)) {
                    if (strpos($contentB[$k], 'R') !== false && $aord != $arraysize - 1) {
                        $this->x += $this->spanborddet['R']['w'];
                    }
                }
                // *********** SPAN BACKGROUND COLOR OFF - RESET BLOCK BGCOLOR ***************** //
                if (isset($spanfill) && $spanfill) {
                    $fill = $save_fill;
                    $spanfill = 0;
                    if ($fill) {
                        $this->SetFColor($bcor);
                    }
                }
                if (isset($this->textparam['visibility']) && $this->textparam['visibility'] && $this->visibility != $save_vis) {
                    $this->SetVisibility($save_vis);
                }
            }

            $this->printobjectbuffer($is_table, $blockdir);
            $this->objectbuffer = [];
            $this->ResetSpacing();
        } // END IF CONTENT

        /* -- CSS-IMAGE-FLOAT -- */
        // Update values if set to skipline
        if ($this->floatmargins) {
            $this->_advanceFloatMargins();
        }


        if ($endofblock && $blockstate > 1) {
            // If float exists at this level
            if (isset($this->floatmargins['R']['y1'])) {
                $fry1 = $this->floatmargins['R']['y1'];
            } else {
                $fry1 = 0;
            }
            if (isset($this->floatmargins['L']['y1'])) {
                $fly1 = $this->floatmargins['L']['y1'];
            } else {
                $fly1 = 0;
            }
            if ($this->y < $fry1 || $this->y < $fly1) {
                $drop = max($fry1, $fly1) - $this->y;
                $this->DivLn($drop);
                $this->x = $currentx;
            }
        }
        /* -- END CSS-IMAGE-FLOAT -- */


        // PADDING and BORDER spacing/fill
        if ($endofblock && ($blockstate > 1) && ($this->blk[$this->blklvl]['padding_bottom'] || $this->blk[$this->blklvl]['border_bottom'] || $this->blk[$this->blklvl]['css_set_height']) && (!$is_table)) {
            // If CSS height set, extend bottom - if on same page as block started, and CSS HEIGHT > actual height,
            // and does not force pagebreak
            $extra = 0;
            if (isset($this->blk[$this->blklvl]['css_set_height']) && $this->blk[$this->blklvl]['css_set_height'] && $this->blk[$this->blklvl]['startpage'] == $this->page) {
                // predicted height
                $h1 = ($this->y - $this->blk[$this->blklvl]['y0']) + $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w'];
                if ($h1 < ($this->blk[$this->blklvl]['css_set_height'] + $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['padding_top'])) {
                    $extra = ($this->blk[$this->blklvl]['css_set_height'] + $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['padding_top']) - $h1;
                }
                if ($this->y + $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w'] + $extra > $this->PageBreakTrigger) {
                    $extra = $this->PageBreakTrigger - ($this->y + $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w']);
                }
            }

            // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
            $this->DivLn($this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w'] + $extra, -3, true, false, 2);
            $this->x = $currentx;

            if ($this->ColActive) {
                $this->breakpoints[$this->CurrCol][] = $this->y;
            } // *COLUMNS*
        }

        // SET Bottom y1 of block (used for painting borders)
        if (($endofblock) && ($blockstate > 1) && (!$is_table)) {
            $this->blk[$this->blklvl]['y1'] = $this->y;
        }

        // BOTTOM MARGIN
        if (($endofblock) && ($blockstate > 1) && ($this->blk[$this->blklvl]['margin_bottom']) && (!$is_table)) {
            if ($this->y + $this->blk[$this->blklvl]['margin_bottom'] < $this->PageBreakTrigger and ! $this->InFooter) {
                $this->DivLn($this->blk[$this->blklvl]['margin_bottom'], $this->blklvl - 1, true, $this->blk[$this->blklvl]['margin_collapse']);
                if ($this->ColActive) {
                    $this->breakpoints[$this->CurrCol][] = $this->y;
                } // *COLUMNS*
            }
        }

        // Reset lineheight
        $stackHeight = $this->divheight;
    }

    public function printobjectbuffer($is_table = false, $blockdir = false)
    {
        if (!$blockdir) {
            $blockdir = $this->directionality;
        }

        if ($is_table && $this->shrin_k > 1) {
            $k = $this->shrin_k;
        } else {
            $k = 1;
        }

        $save_y = $this->y;
        $save_x = $this->x;

        $save_currentfontfamily = $this->FontFamily;
        $save_currentfontsize = $this->FontSizePt;
        $save_currentfontstyle = $this->FontStyle;

        if ($blockdir == 'rtl') {
            $rtlalign = 'R';
        } else {
            $rtlalign = 'L';
        }

        foreach ($this->objectbuffer as $ib => $objattr) {

            switch($objattr['type']){
                case 'bookmark':
                case 'indexentry':
                case 'toc':
                    $x = $objattr['OUTER-X'];
                    $y = $objattr['OUTER-Y'];
                    $this->y = $y - $this->FontSize / 2;
                    $this->x = $x;

                    switch($objattr['type']){
                        case 'bookmark':
                            $this->Bookmark($objattr['CONTENT'], $objattr['bklevel'], $y - $this->FontSize);
                            break;
                        case 'indexentry':
                            $this->IndexEntry($objattr['CONTENT']);
                            break;
                        case 'toc':
                            $this->TOC_Entry($objattr['CONTENT'], $objattr['toclevel'], (isset($objattr['toc_id']) ? $objattr['toc_id'] : ''));
                            break;
                    }
                    break;

                /* -- ANNOTATIONS -- */
                case 'annot':
                    if ($objattr['POS-X']) {
                        $x = $objattr['POS-X'];
                    } elseif ($this->annotMargin <> 0) {
                        $x = -$objattr['OUTER-X'];
                    } else {
                        $x = $objattr['OUTER-X'];
                    }
                    if ($objattr['POS-Y']) {
                        $y = $objattr['POS-Y'];
                    } else {
                        $y = $objattr['OUTER-Y'] - $this->FontSize / 2;
                    }
                    // Create a dummy entry in the _out/columnBuffer with position sensitive data,
                    // linking $y-1 in the Columnbuffer with entry in $this->columnAnnots
                    // and when columns are split in length will not break annotation from current line
                    $this->y = $y - 1;
                    $this->x = $x - 1;
                    $this->Line($x - 1, $y - 1, $x - 1, $y - 1);
                    $this->Annotation($objattr['CONTENT'], $x, $y, $objattr['ICON'], $objattr['AUTHOR'], $objattr['SUBJECT'], $objattr['OPACITY'], $objattr['COLOR'], (isset($objattr['POPUP']) ? $objattr['POPUP'] : ''), (isset($objattr['FILE']) ? $objattr['FILE'] : ''));
                    break;

                default:
                    $y = $objattr['OUTER-Y'];
                    $x = $objattr['OUTER-X'];
                    $w = $objattr['OUTER-WIDTH'];
                    $h = $objattr['OUTER-HEIGHT'];
                    if (isset($objattr['text'])) {
                        $texto = $objattr['text'];
                    }
                    $this->y = $y;
                    $this->x = $x;
                    if (isset($objattr['fontfamily'])) {
                        $this->SetFont($objattr['fontfamily'], '', $objattr['fontsize']);
                    }
            }

            switch($objattr['type']){
                case 'hr':
                    $this->SetDColor($objattr['color']);
                    switch ($objattr['align']) {
                        case 'C':
                            $empty = $objattr['OUTER-WIDTH'] - $objattr['INNER-WIDTH'];
                            $empty /= 2;
                            $x += $empty;
                            break;
                        case 'R':
                            $empty = $objattr['OUTER-WIDTH'] - $objattr['INNER-WIDTH'];
                            $x += $empty;
                            break;
                    }
                    $oldlinewidth = $this->LineWidth;
                    $this->SetLineWidth($objattr['linewidth'] / $k);
                    $this->y += ($objattr['linewidth'] / 2) + $objattr['margin_top'] / $k;
                    $this->Line($x, $this->y, $x + $objattr['INNER-WIDTH'], $this->y);
                    $this->SetLineWidth($oldlinewidth);
                    $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                    break;

                case 'image':
                    // mPDF 5.7.3 TRANSFORMS
                    if (isset($objattr['transform'])) {
                        $this->_out("\n" . '% BTR'); // Begin Transform
                    }
                    if (isset($objattr['z-index']) && $objattr['z-index'] > 0 && $this->current_layer == 0) {
                        $this->BeginLayer($objattr['z-index']);
                    }
                    if (isset($objattr['visibility']) && $objattr['visibility'] != 'visible' && $objattr['visibility']) {
                        $this->SetVisibility($objattr['visibility']);
                    }
                    if (isset($objattr['opacity'])) {
                        $this->SetAlpha($objattr['opacity']);
                    }

                    $obiw = $objattr['INNER-WIDTH'];
                    $obih = $objattr['INNER-HEIGHT'];

                    $sx = $objattr['orig_w'] ? ($objattr['INNER-WIDTH'] * Mpdf::SCALE / $objattr['orig_w']) : INF;
                    $sy = $objattr['orig_h'] ? ($objattr['INNER-HEIGHT'] * Mpdf::SCALE / $objattr['orig_h']) : INF;

                    $rotate = 0;
                    if (isset($objattr['ROTATE'])) {
                        $rotate = $objattr['ROTATE'];
                    }

                    if ($rotate == 90) {
                        // Clockwise
                        $obiw = $objattr['INNER-HEIGHT'];
                        $obih = $objattr['INNER-WIDTH'];
                        $tr = $this->transformTranslate(0, -$objattr['INNER-WIDTH'], true);
                        $tr .= ' ' . $this->transformRotate(90, $objattr['INNER-X'], ($objattr['INNER-Y'] + $objattr['INNER-WIDTH']), true);
                        $sx = $obiw * Mpdf::SCALE / $objattr['orig_h'];
                        $sy = $obih * Mpdf::SCALE / $objattr['orig_w'];
                    } elseif ($rotate == -90 || $rotate == 270) {
                        // AntiClockwise
                        $obiw = $objattr['INNER-HEIGHT'];
                        $obih = $objattr['INNER-WIDTH'];
                        $tr = $this->transformTranslate($objattr['INNER-WIDTH'], ($objattr['INNER-HEIGHT'] - $objattr['INNER-WIDTH']), true);
                        $tr .= ' ' . $this->transformRotate(-90, $objattr['INNER-X'], ($objattr['INNER-Y'] + $objattr['INNER-WIDTH']), true);
                        $sx = $obiw * Mpdf::SCALE / $objattr['orig_h'];
                        $sy = $obih * Mpdf::SCALE / $objattr['orig_w'];
                    } elseif ($rotate == 180) {
                        // Mirror
                        $tr = $this->transformTranslate($objattr['INNER-WIDTH'], -$objattr['INNER-HEIGHT'], true);
                        $tr .= ' ' . $this->transformRotate(180, $objattr['INNER-X'], ($objattr['INNER-Y'] + $objattr['INNER-HEIGHT']), true);
                    } else {
                        $tr = '';
                    }
                    $tr = trim($tr);
                    if ($tr) {
                        $tr .= ' ';
                    }
                    $gradmask = '';

                    // mPDF 5.7.3 TRANSFORMS
                    $tr2 = '';
                    if (isset($objattr['transform'])) {
                        $maxsize_x = $w;
                        $maxsize_y = $h;
                        $cx = $x + $w / 2;
                        $cy = $y + $h / 2;
                        preg_match_all('/(translatex|translatey|translate|scalex|scaley|scale|rotate|skewX|skewY|skew)\((.*?)\)/is', $objattr['transform'], $m);
                        if (count($m[0])) {
                            for ($i = 0; $i < count($m[0]); $i++) {
                                $c = strtolower($m[1][$i]);
                                $v = trim($m[2][$i]);
                                $vv = preg_split('/[ ,]+/', $v);
                                if ($c == 'translate' && count($vv)) {
                                    $translate_x = $this->sizeConverter->convert($vv[0], $maxsize_x, false, false);
                                    if (count($vv) == 2) {
                                        $translate_y = $this->sizeConverter->convert($vv[1], $maxsize_y, false, false);
                                    } else {
                                        $translate_y = 0;
                                    }
                                    $tr2 .= $this->transformTranslate($translate_x, $translate_y, true) . ' ';
                                } elseif ($c == 'translatex' && count($vv)) {
                                    $translate_x = $this->sizeConverter->convert($vv[0], $maxsize_x, false, false);
                                    $tr2 .= $this->transformTranslate($translate_x, 0, true) . ' ';
                                } elseif ($c == 'translatey' && count($vv)) {
                                    $translate_y = $this->sizeConverter->convert($vv[1], $maxsize_y, false, false);
                                    $tr2 .= $this->transformTranslate(0, $translate_y, true) . ' ';
                                } elseif ($c == 'scale' && count($vv)) {
                                    $scale_x = $vv[0] * 100;
                                    if (count($vv) == 2) {
                                        $scale_y = $vv[1] * 100;
                                    } else {
                                        $scale_y = $scale_x;
                                    }
                                    $tr2 .= $this->transformScale($scale_x, $scale_y, $cx, $cy, true) . ' ';
                                } elseif ($c == 'scalex' && count($vv)) {
                                    $scale_x = $vv[0] * 100;
                                    $tr2 .= $this->transformScale($scale_x, 0, $cx, $cy, true) . ' ';
                                } elseif ($c == 'scaley' && count($vv)) {
                                    $scale_y = $vv[1] * 100;
                                    $tr2 .= $this->transformScale(0, $scale_y, $cx, $cy, true) . ' ';
                                } elseif ($c == 'skew' && count($vv)) {
                                    $angle_x = $this->ConvertAngle($vv[0], false);
                                    if (count($vv) == 2) {
                                        $angle_y = $this->ConvertAngle($vv[1], false);
                                    } else {
                                        $angle_y = 0;
                                    }
                                    $tr2 .= $this->transformSkew($angle_x, $angle_y, $cx, $cy, true) . ' ';
                                } elseif ($c == 'skewx' && count($vv)) {
                                    $angle = $this->ConvertAngle($vv[0], false);
                                    $tr2 .= $this->transformSkew($angle, 0, $cx, $cy, true) . ' ';
                                } elseif ($c == 'skewy' && count($vv)) {
                                    $angle = $this->ConvertAngle($vv[0], false);
                                    $tr2 .= $this->transformSkew(0, $angle, $cx, $cy, true) . ' ';
                                } elseif ($c == 'rotate' && count($vv)) {
                                    $angle = $this->ConvertAngle($vv[0]);
                                    $tr2 .= $this->transformRotate($angle, $cx, $cy, true) . ' ';
                                }
                            }
                        }
                    }

                    // LIST MARKERS (Images)    // mPDF 6  Lists
                    if (isset($objattr['listmarker']) && $objattr['listmarker'] && $objattr['listmarkerposition'] == 'outside') {
                        $mw = $objattr['OUTER-WIDTH'];
                        // NB If change marker-offset, also need to alter in function _getListMarkerWidth
                        $adjx = $this->sizeConverter->convert($this->list_marker_offset, $this->FontSize);
                        if ($objattr['dir'] == 'rtl') {
                            $objattr['INNER-X'] += $adjx;
                        } else {
                            $objattr['INNER-X'] -= $adjx;
                            $objattr['INNER-X'] -= $mw;
                        }
                    }
                    // mPDF 5.7.3 TRANSFORMS / BACKGROUND COLOR
                    // Transform also affects image background
                    if ($tr2) {
                        $this->_out('q ' . $tr2 . ' ');
                    }
                    if (isset($objattr['bgcolor']) && $objattr['bgcolor']) {
                        $bgcol = $objattr['bgcolor'];
                        $this->SetFColor($bgcol);
                        $this->Rect($x, $y, $w, $h, 'F');
                        $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
                    }
                    if ($tr2) {
                        $this->_out('Q');
                    }

                    /* -- BACKGROUNDS -- */
                    if (isset($objattr['GRADIENT-MASK'])) {
                        $g = $this->gradient->parseMozGradient($objattr['GRADIENT-MASK']);
                        if ($g) {
                            $dummy = $this->gradient->Gradient($objattr['INNER-X'], $objattr['INNER-Y'], $obiw, $obih, $g['type'], $g['stops'], $g['colorspace'], $g['coords'], $g['extend'], true, true);
                            $gradmask = '/TGS' . count($this->gradients) . ' gs ';
                        }
                    }
                    /* -- END BACKGROUNDS -- */
                    /* -- IMAGES-WMF -- */
                    if (isset($objattr['itype']) && $objattr['itype'] == 'wmf') {
                        $outstring = sprintf('q ' . $tr . $tr2 . '%.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, -$sy, $objattr['INNER-X'] * Mpdf::SCALE - $sx * $objattr['wmf_x'], (($this->h - $objattr['INNER-Y']) * Mpdf::SCALE) + $sy * $objattr['wmf_y'], $objattr['ID']); // mPDF 5.7.3 TRANSFORMS
                    } else {                /* -- END IMAGES-WMF -- */
                        if (isset($objattr['itype']) && $objattr['itype'] == 'svg') {
                            $outstring = sprintf('q ' . $tr . $tr2 . '%.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, -$sy, $objattr['INNER-X'] * Mpdf::SCALE - $sx * $objattr['wmf_x'], (($this->h - $objattr['INNER-Y']) * Mpdf::SCALE) + $sy * $objattr['wmf_y'], $objattr['ID']); // mPDF 5.7.3 TRANSFORMS
                        } else {
                            $outstring = sprintf("q " . $tr . $tr2 . "%.3F 0 0 %.3F %.3F %.3F cm " . $gradmask . "/I%d Do Q", $obiw * Mpdf::SCALE, $obih * Mpdf::SCALE, $objattr['INNER-X'] * Mpdf::SCALE, ($this->h - ($objattr['INNER-Y'] + $obih )) * Mpdf::SCALE, $objattr['ID']); // mPDF 5.7.3 TRANSFORMS
                        }
                    }
                    $this->_out($outstring);
                    // LINK
                    if (isset($objattr['link'])) {
                        $this->Link($objattr['INNER-X'], $objattr['INNER-Y'], $objattr['INNER-WIDTH'], $objattr['INNER-HEIGHT'], $objattr['link']);
                    }
                    if (isset($objattr['opacity'])) {
                        $this->SetAlpha(1);
                    }

                    // mPDF 5.7.3 TRANSFORMS
                    // Transform also affects image borders
                    if ($tr2) {
                        $this->_out('q ' . $tr2 . ' ');
                    }
                    if ((isset($objattr['border_top']) && $objattr['border_top'] > 0) || (isset($objattr['border_left']) && $objattr['border_left'] > 0) || (isset($objattr['border_right']) && $objattr['border_right'] > 0) || (isset($objattr['border_bottom']) && $objattr['border_bottom'] > 0)) {
                        $this->PaintImgBorder($objattr, $is_table);
                    }
                    if ($tr2) {
                        $this->_out('Q');
                    }

                    if (isset($objattr['visibility']) && $objattr['visibility'] != 'visible' && $objattr['visibility']) {
                        $this->SetVisibility('visible');
                    }
                    if (isset($objattr['z-index']) && $objattr['z-index'] > 0 && $this->current_layer == 0) {
                        $this->EndLayer();
                    }
                    // mPDF 5.7.3 TRANSFORMS
                    if (isset($objattr['transform'])) {
                        $this->_out("\n" . '% ETR'); // End Transform
                    }
                    break;

                case 'barcode':
                    $bgcol = $this->colorConverter->convert(255, $this->PDFAXwarnings);

                    if (isset($objattr['bgcolor']) && $objattr['bgcolor']) {
                        $bgcol = $objattr['bgcolor'];
                    }

                    $col = $this->colorConverter->convert(0, $this->PDFAXwarnings);

                    if (isset($objattr['color']) && $objattr['color']) {
                        $col = $objattr['color'];
                    }

                    $this->SetFColor($bgcol);
                    $this->Rect($objattr['BORDER-X'], $objattr['BORDER-Y'], $objattr['BORDER-WIDTH'], $objattr['BORDER-HEIGHT'], 'F');
                    $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));

                    if (isset($objattr['BORDER-WIDTH'])) {
                        $this->PaintImgBorder($objattr, $is_table);
                    }

                    $barcodeTypes = ['EAN13', 'ISBN', 'ISSN', 'UPCA', 'UPCE', 'EAN8'];
                    if (in_array($objattr['btype'], $barcodeTypes, true)) {

                        $this->WriteBarcode(
                            $objattr['code'],
                            $objattr['showtext'],
                            $objattr['INNER-X'],
                            $objattr['INNER-Y'],
                            $objattr['bsize'],
                            0,
                            0,
                            0,
                            0,
                            0,
                            $objattr['bheight'],
                            $bgcol,
                            $col,
                            $objattr['btype'],
                            $objattr['bsupp'],
                            (isset($objattr['bsupp_code']) ? $objattr['bsupp_code'] : ''),
                            $k
                        );

                    } elseif ($objattr['btype'] === 'QR') {

                        $barcodeContent = str_replace('\r\n', "\r\n", $objattr['code']);
                        $barcodeContent = str_replace('\n', "\n", $barcodeContent);

                        $this->qrcode = new QrCode\QrCode($barcodeContent, $objattr['errorlevel']);
                        if ($objattr['disableborder']) {
                            $this->qrcode->disableBorder();
                        }

                        $bgColor = [255, 255, 255];
                        if ($objattr['bgcolor']) {
                            $bgColor = array_map(
                                function ($col) {
                                    return intval(255 * floatval($col));
                                },
                                explode(" ", $this->SetColor($objattr['bgcolor'], 'CodeOnly'))
                            );
                        }
                        $color = [0, 0, 0];
                        if ($objattr['color']) {
                            $color = array_map(
                                function ($col) {
                                    return intval(255 * floatval($col));
                                },
                                explode(" ", $this->SetColor($objattr['color'], 'CodeOnly'))
                            );
                        }

                        $this->qrcode->displayFPDF(
                            $this,
                            $objattr['INNER-X'],
                            $objattr['INNER-Y'],
                            $objattr['bsize'] * 25,
                            $bgColor,
                            $color
                        );

                    } else {

                        $this->WriteBarcode2(
                            $objattr['code'],
                            $objattr['INNER-X'],
                            $objattr['INNER-Y'],
                            $objattr['bsize'],
                            $objattr['bheight'],
                            $bgcol,
                            $col,
                            $objattr['btype'],
                            $objattr['pr_ratio'],
                            $k
                        );

                    }
                    break;

                case 'textcircle':
                    $bgcol = '';
                    if (isset($objattr['bgcolor']) && $objattr['bgcolor']) {
                        $bgcol = $objattr['bgcolor'];
                    }
                    $col = $this->colorConverter->convert(0, $this->PDFAXwarnings);
                    if (isset($objattr['color']) && $objattr['color']) {
                        $col = $objattr['color'];
                    }
                    $this->SetTColor($col);
                    $this->SetFColor($bgcol);
                    if ($bgcol) {
                        $this->Rect($objattr['BORDER-X'], $objattr['BORDER-Y'], $objattr['BORDER-WIDTH'], $objattr['BORDER-HEIGHT'], 'F');
                    }
                    $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
                    if (isset($objattr['BORDER-WIDTH'])) {
                        $this->PaintImgBorder($objattr, $is_table);
                    }
                    if (empty($this->directWrite)) {
                        $this->directWrite = new DirectWrite($this, $this->otl, $this->sizeConverter, $this->colorConverter);
                    }
                    if (isset($objattr['top-text'])) {
                        $this->directWrite->CircularText($objattr['INNER-X'] + $objattr['INNER-WIDTH'] / 2, $objattr['INNER-Y'] + $objattr['INNER-HEIGHT'] / 2, $objattr['r'] / $k, $objattr['top-text'], 'top', $objattr['fontfamily'], $objattr['fontsize'] / $k, $objattr['fontstyle'], $objattr['space-width'], $objattr['char-width'], (isset($objattr['divider']) ? $objattr['divider'] : ''));
                    }
                    if (isset($objattr['bottom-text'])) {
                        $this->directWrite->CircularText($objattr['INNER-X'] + $objattr['INNER-WIDTH'] / 2, $objattr['INNER-Y'] + $objattr['INNER-HEIGHT'] / 2, $objattr['r'] / $k, $objattr['bottom-text'], 'bottom', $objattr['fontfamily'], $objattr['fontsize'] / $k, $objattr['fontstyle'], $objattr['space-width'], $objattr['char-width'], (isset($objattr['divider']) ? $objattr['divider'] : ''));
                    }
                    break;
            }

            $this->ResetSpacing();

            switch($objattr['type']){
                // LIST MARKERS (Text or bullets)   // mPDF 6  Lists
                case 'listmarker':
                    if (isset($objattr['fontfamily'])) {
                        $this->SetFont($objattr['fontfamily'], $objattr['fontstyle'], $objattr['fontsizept']);
                    }
                    $col = $this->colorConverter->convert(0, $this->PDFAXwarnings);
                    if (isset($objattr['colorarray']) && ($objattr['colorarray'])) {
                        $col = $objattr['colorarray'];
                    }

                    if (isset($objattr['bullet']) && $objattr['bullet']) { // Used for position "outside" only
                        $type = $objattr['bullet'];
                        $size = $objattr['size'];

                        if ($objattr['listmarkerposition'] == 'inside') {
                            $adjx = $size / 2;
                            if ($objattr['dir'] == 'rtl') {
                                $adjx += $objattr['offset'];
                            }
                            $this->x += $adjx;
                        } else {
                            $adjx = $objattr['offset'];
                            $adjx += $size / 2;
                            if ($objattr['dir'] == 'rtl') {
                                $this->x += $adjx;
                            } else {
                                $this->x -= $adjx;
                            }
                        }

                        $yadj = $objattr['lineBox']['glyphYorigin'];
                        if (isset($this->CurrentFont['desc']['XHeight']) && $this->CurrentFont['desc']['XHeight']) {
                            $xh = $this->CurrentFont['desc']['XHeight'];
                        } else {
                            $xh = 500;
                        }
                        $yadj -= ($this->FontSize * $xh / 1000) * 0.625; // Vertical height of bullet (centre) from baseline= XHeight * 0.625
                        $this->y += $yadj;

                        $this->_printListBullet($this->x, $this->y, $size, $type, $col);
                    } else {
                        $this->SetTColor($col);
                        $w = $this->GetStringWidth($texto);
                        // NB If change marker-offset, also need to alter in function _getListMarkerWidth
                        $adjx = $this->sizeConverter->convert($this->list_marker_offset, $this->FontSize);
                        if ($objattr['dir'] == 'rtl') {
                            $align = 'L';
                            $this->x += $adjx;
                        } else {
                            // Use these lines to set as marker-offset, right-aligned - default
                            $align = 'R';
                            $this->x -= $adjx;
                            $this->x -= $w;
                        }
                        $this->Cell($w, $this->FontSize, $texto, 0, 0, $align, 0, '', 0, 0, 0, 'T', 0, false, false, 0, $objattr['lineBox']);
                        $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                    }
                    break;

                case 'dottab':
                    if (isset($objattr['fontfamily'])) {
                        $this->SetFont($objattr['fontfamily'], '', $objattr['fontsize']);
                    }
                    $sp = $this->GetStringWidth(' ');
                    $nb = floor(($w - 2 * $sp) / $this->GetStringWidth('.'));
                    if ($nb > 0) {
                        $dots = ' ' . str_repeat('.', $nb) . ' ';
                    } else {
                        $dots = ' ';
                    }
                    $col = $this->colorConverter->convert(0, $this->PDFAXwarnings);
                    if (isset($objattr['colorarray']) && ($objattr['colorarray'])) {
                        $col = $objattr['colorarray'];
                    }
                    $this->SetTColor($col);
                    $save_dh = $this->divheight;
                    $save_sbd = $this->spanborddet;
                    $save_textvar = $this->textvar; // mPDF 5.7.1
                    $this->spanborddet = '';
                    $this->divheight = 0;
                    $this->textvar = 0x00; // mPDF 5.7.1

                    $this->Cell($w, $h, $dots, 0, 0, 'C', 0, '', 0, 0, 0, 'T', 0, false, false, 0, $objattr['lineBox']); // mPDF 6 DOTTAB
                    $this->spanborddet = $save_sbd;
                    $this->textvar = $save_textvar; // mPDF 5.7.1
                    $this->divheight = $save_dh;
                    $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));

                    break;

                case 'input':
                    switch($objattr['subtype']){
                        case 'TEXT':
                        case 'PASSWORD':
                            $this->form->print_ob_text($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir);
                            break;

                        // INPUT/BUTTON as IMAGE
                        case 'IMAGE':
                            $this->form->print_ob_imageinput($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $is_table);
                            break;

                        case 'SUBMIT':
                        case 'RESET':
                        case 'BUTTON':
                            $this->form->print_ob_button($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir);
                            break;

                        case 'CHECKBOX':
                            $this->form->print_ob_checkbox($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y);
                            break;

                        case 'RADIO':
                            $this->form->print_ob_radio($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y);
                            break;
                    }
                    break;

                case 'textarea':
                    $this->form->print_ob_textarea($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir);
                    break;

                case 'select':
                    $this->form->print_ob_select($objattr, $w, $h, $texto, $rtlalign, $k, $blockdir);
                    break;
            }
        }

        $this->SetFont($save_currentfontfamily, $save_currentfontstyle, $save_currentfontsize);

        $this->y = $save_y;
        $this->x = $save_x;

        unset($content);
    }

    public function WriteFlowingBlock($s, $sOTLdata)
    {
    // mPDF 5.7.1
        $currentx = $this->x;
        $is_table = $this->flowingBlockAttr['is_table'];
        $table_draft = $this->flowingBlockAttr['table_draft'];
        // width of all the content so far in points
        $contentWidth = & $this->flowingBlockAttr['contentWidth'];
        // cell width in points
        $maxWidth = & $this->flowingBlockAttr['width'];
        $lineCount = & $this->flowingBlockAttr['lineCount'];
        // line height in user units
        $stackHeight = & $this->flowingBlockAttr['height'];
        $align = & $this->flowingBlockAttr['align'];
        $content = & $this->flowingBlockAttr['content'];
        $contentB = & $this->flowingBlockAttr['contentB'];
        $font = & $this->flowingBlockAttr['font'];
        $valign = & $this->flowingBlockAttr['valign'];
        $blockstate = $this->flowingBlockAttr['blockstate'];
        $cOTLdata = & $this->flowingBlockAttr['cOTLdata']; // mPDF 5.7.1

        $newblock = $this->flowingBlockAttr['newblock'];
        $blockdir = $this->flowingBlockAttr['blockdir'];

        // *********** BLOCK BACKGROUND COLOR ***************** //
        if ($this->blk[$this->blklvl]['bgcolor'] && !$is_table) {
            $fill = 0;
        } else {
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
            $fill = 0;
        }
        $font[] = $this->saveFont();
        $content[] = '';
        $contentB[] = '';
        $cOTLdata[] = $sOTLdata; // mPDF 5.7.1
        $currContent = & $content[count($content) - 1];

        $CJKoverflow = false;
        $Oikomi = false; // mPDF 6
        $hanger = '';

        // COLS
        $oldcolumn = $this->CurrCol;
        if ($this->ColActive && !$is_table) {
            $this->breakpoints[$this->CurrCol][] = $this->y;
        } // *COLUMNS*

        /* -- TABLES -- */
        if ($is_table) {
            $ipaddingL = 0;
            $ipaddingR = 0;
            $paddingL = 0;
            $paddingR = 0;
            $cpaddingadjustL = 0;
            $cpaddingadjustR = 0;
            // Added mPDF 3.0
            $fpaddingR = 0;
            $fpaddingL = 0;
        } else {
            /* -- END TABLES -- */
            $ipaddingL = $this->blk[$this->blklvl]['padding_left'];
            $ipaddingR = $this->blk[$this->blklvl]['padding_right'];
            $paddingL = ($ipaddingL * Mpdf::SCALE);
            $paddingR = ($ipaddingR * Mpdf::SCALE);
            $this->cMarginL = $this->blk[$this->blklvl]['border_left']['w'];
            $cpaddingadjustL = -$this->cMarginL;
            $this->cMarginR = $this->blk[$this->blklvl]['border_right']['w'];
            $cpaddingadjustR = -$this->cMarginR;
            // Added mPDF 3.0 Float DIV
            $fpaddingR = 0;
            $fpaddingL = 0;
            /* -- CSS-FLOAT -- */
            if (count($this->floatDivs)) {
                list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($this->blklvl);
                if ($r_exists) {
                    $fpaddingR = $r_width;
                }
                if ($l_exists) {
                    $fpaddingL = $l_width;
                }
            }
            /* -- END CSS-FLOAT -- */

            $usey = $this->y + 0.002;
            if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 0)) {
                $usey += $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
            }
            /* -- CSS-IMAGE-FLOAT -- */
            // If float exists at this level
            if (isset($this->floatmargins['R']) && $usey <= $this->floatmargins['R']['y1'] && $usey >= $this->floatmargins['R']['y0'] && !$this->floatmargins['R']['skipline']) {
                $fpaddingR += $this->floatmargins['R']['w'];
            }
            if (isset($this->floatmargins['L']) && $usey <= $this->floatmargins['L']['y1'] && $usey >= $this->floatmargins['L']['y0'] && !$this->floatmargins['L']['skipline']) {
                $fpaddingL += $this->floatmargins['L']['w'];
            }
            /* -- END CSS-IMAGE-FLOAT -- */
        } // *TABLES*
        // OBJECTS - IMAGES & FORM Elements (NB has already skipped line/page if required - in printbuffer)
        if (substr($s, 0, 3) == "\xbb\xa4\xac") { // identifier has been identified!
            $objattr = $this->_getObjAttr($s);
            $h_corr = 0;
            if ($is_table) { // *TABLES*
                $maximumW = ($maxWidth / Mpdf::SCALE) - ($this->cellPaddingL + $this->cMarginL + $this->cellPaddingR + $this->cMarginR);  // *TABLES*
            } // *TABLES*
            else { // *TABLES*
                if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 0) && (!$is_table)) {
                    $h_corr = $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
                }
                $maximumW = ($maxWidth / Mpdf::SCALE) - ($this->blk[$this->blklvl]['padding_left'] + $this->blk[$this->blklvl]['border_left']['w'] + $this->blk[$this->blklvl]['padding_right'] + $this->blk[$this->blklvl]['border_right']['w'] + $fpaddingL + $fpaddingR );
            } // *TABLES*
            $objattr = $this->inlineObject($objattr['type'], $this->lMargin + $fpaddingL + ($contentWidth / Mpdf::SCALE), ($this->y + $h_corr), $objattr, $this->lMargin, ($contentWidth / Mpdf::SCALE), $maximumW, $stackHeight, true, $is_table);

            // SET LINEHEIGHT for this line ================ RESET AT END
            $stackHeight = max($stackHeight, $objattr['OUTER-HEIGHT']);
            $this->objectbuffer[count($content) - 1] = $objattr;
            // if (isset($objattr['vertical-align'])) { $valign = $objattr['vertical-align']; }
            // else { $valign = ''; }
            // LIST MARKERS // mPDF 6  Lists
            if ($objattr['type'] == 'image' && isset($objattr['listmarker']) && $objattr['listmarker'] && $objattr['listmarkerposition'] == 'outside') {
                // do nothing
            } else {
                $contentWidth += ($objattr['OUTER-WIDTH'] * Mpdf::SCALE);
            }
            return;
        }

        $lbw = $rbw = 0; // Border widths
        if (!empty($this->spanborddet)) {
            if (isset($this->spanborddet['L'])) {
                $lbw = $this->spanborddet['L']['w'];
            }
            if (isset($this->spanborddet['R'])) {
                $rbw = $this->spanborddet['R']['w'];
            }
        }

        if ($this->usingCoreFont) {
            $clen = strlen($s);
        } else {
            $clen = mb_strlen($s, $this->mb_enc);
        }

        // for every character in the string
        for ($i = 0; $i < $clen; $i++) {
            // extract the current character
            // get the width of the character in points
            if ($this->usingCoreFont) {
                $c = $s[$i];
                // Soft Hyphens chr(173)
                $cw = ($this->GetCharWidthCore($c) * Mpdf::SCALE);
                if (($this->textvar & TextVars::FC_KERNING) && $i > 0) { // mPDF 5.7.1
                    if (isset($this->CurrentFont['kerninfo'][$s[($i - 1)]][$c])) {
                        $cw += ($this->CurrentFont['kerninfo'][$s[($i - 1)]][$c] * $this->FontSizePt / 1000 );
                    }
                }
            } else {
                $c = mb_substr($s, $i, 1, $this->mb_enc);
                $cw = ($this->GetCharWidthNonCore($c, false) * Mpdf::SCALE);
                // mPDF 5.7.1
                // Use OTL GPOS
                if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF)) {
                    // ...WriteFlowingBlock...
                    // Only  add XAdvanceL (not sure at present whether RTL or LTR writing direction)
                    // At this point, XAdvanceL and XAdvanceR will balance
                    if (isset($sOTLdata['GPOSinfo'][$i]['XAdvanceL'])) {
                        $cw += $sOTLdata['GPOSinfo'][$i]['XAdvanceL'] * (1000 / $this->CurrentFont['unitsPerEm']) * ($this->FontSize / 1000) * Mpdf::SCALE;
                    }
                }
                if (($this->textvar & TextVars::FC_KERNING) && $i > 0) { // mPDF 5.7.1
                    $lastc = mb_substr($s, ($i - 1), 1, $this->mb_enc);
                    $ulastc = $this->UTF8StringToArray($lastc, false);
                    $uc = $this->UTF8StringToArray($c, false);
                    if (isset($this->CurrentFont['kerninfo'][$ulastc[0]][$uc[0]])) {
                        $cw += ($this->CurrentFont['kerninfo'][$ulastc[0]][$uc[0]] * $this->FontSizePt / 1000 );
                    }
                }
            }

            if ($i == 0) {
                $cw += $lbw * Mpdf::SCALE;
                $contentB[(count($contentB) - 1)] .= 'L';
            }
            if ($i == ($clen - 1)) {
                $cw += $rbw * Mpdf::SCALE;
                $contentB[(count($contentB) - 1)] .= 'R';
            }
            if ($c == ' ') {
                $currContent .= $c;
                $contentWidth += $cw;
                continue;
            }

            // Paragraph INDENT
            $WidthCorrection = 0;
            if (($newblock) && ($blockstate == 1 || $blockstate == 3) && isset($this->blk[$this->blklvl]['text_indent']) && ($lineCount == 0) && (!$is_table) && ($align != 'C')) {
                $ti = $this->sizeConverter->convert($this->blk[$this->blklvl]['text_indent'], $this->blk[$this->blklvl]['inner_width'], $this->blk[$this->blklvl]['InlineProperties']['size'], false);  // mPDF 5.7.4
                $WidthCorrection = ($ti * Mpdf::SCALE);
            }
            // OUTDENT
            foreach ($this->objectbuffer as $k => $objattr) {   // mPDF 6 DOTTAB
                if ($objattr['type'] == 'dottab') {
                    $WidthCorrection -= ($objattr['outdent'] * Mpdf::SCALE);
                    break;
                }
            }


            // Added mPDF 3.0 Float DIV
            $fpaddingR = 0;
            $fpaddingL = 0;
            /* -- CSS-FLOAT -- */
            if (count($this->floatDivs)) {
                list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($this->blklvl);
                if ($r_exists) {
                    $fpaddingR = $r_width;
                }
                if ($l_exists) {
                    $fpaddingL = $l_width;
                }
            }
            /* -- END CSS-FLOAT -- */

            $usey = $this->y + 0.002;
            if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 0)) {
                $usey += $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
            }

            /* -- CSS-IMAGE-FLOAT -- */
            // If float exists at this level
            if (isset($this->floatmargins['R']) && $usey <= $this->floatmargins['R']['y1'] && $usey >= $this->floatmargins['R']['y0'] && !$this->floatmargins['R']['skipline']) {
                $fpaddingR += $this->floatmargins['R']['w'];
            }
            if (isset($this->floatmargins['L']) && $usey <= $this->floatmargins['L']['y1'] && $usey >= $this->floatmargins['L']['y0'] && !$this->floatmargins['L']['skipline']) {
                $fpaddingL += $this->floatmargins['L']['w'];
            }
            /* -- END CSS-IMAGE-FLOAT -- */


            // try adding another char
            if (( $contentWidth + $cw > $maxWidth - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) ) + 0.001)) {// 0.001 is to correct for deviations converting mm=>pts
                // it won't fit, output what we already have
                $lineCount++;

                // contains any content that didn't make it into this print
                $savedContent = '';
                $savedContentB = '';
                $savedOTLdata = []; // mPDF 5.7.1
                $savedFont = [];
                $savedObj = [];
                $savedPreOTLdata = []; // mPDF 5.7.1
                $savedPreContent = [];
                $savedPreContentB = [];
                $savedPreFont = [];

                // mPDF 6
                // New line-breaking algorithm
                /////////////////////
                // LINE BREAKING
                /////////////////////
                $breakfound = false;
                $contentctr = count($content) - 1;
                if ($this->usingCoreFont) {
                    $charctr = strlen($currContent);
                } else {
                    $charctr = mb_strlen($currContent, $this->mb_enc);
                }
                $checkchar = $c;
                $prevchar = $this->_getPrevChar($contentctr, $charctr, $content);

                /* -- CJK-FONTS -- */
                // 1) CJK Overflowing a) punctuation or b) Oikomi
                // Next character ($c) is suitable to add as overhanging or squeezed punctuation, or Oikomi
                if ($CJKoverflow || $Oikomi) { // If flag already set
                    $CJKoverflow = false;
                    $Oikomi = false;
                    $breakfound = true;
                }
                if (!$this->usingCoreFont && !$breakfound && $this->checkCJK) {

                    // Get next/following character (in this chunk)
                    $followingchar = '';
                    if ($i < ($clen - 1)) {
                        if ($this->usingCoreFont) {
                            $followingchar = $s[$i + 1];
                        } else {
                            $followingchar = mb_substr($s, $i + 1, 1, $this->mb_enc);
                        }
                    }

                    // 1a) Overflow punctuation
                    if (preg_match("/[" . $this->pregCJKchars . "]/u", $prevchar) && preg_match("/[" . $this->CJKoverflow . "]/u", $checkchar) && $this->allowCJKorphans) {
                        // add character onto this line
                        $currContent .= $c;
                        $contentWidth += $cw;
                        $CJKoverflow = true; // Set flag
                        continue;
                    } elseif (preg_match("/[" . $this->pregCJKchars . "]/u", $checkchar) && $this->allowCJKorphans &&
                            (preg_match("/[" . $this->CJKleading . "]/u", $followingchar) || preg_match("/[" . $this->CJKfollowing . "]/u", $checkchar)) &&
                            !preg_match("/[" . $this->CJKleading . "]/u", $checkchar) && !preg_match("/[" . $this->CJKfollowing . "]/u", $followingchar) &&
                            !(preg_match("/[0-9\x{ff10}-\x{ff19}]/u", $followingchar) && preg_match("/[0-9\x{ff10}-\x{ff19}]/u", $checkchar))) {
                        // 1b) Try squeezing another character(s) onto this line = Oikomi, if character cannot end line
                        // or next character cannot start line (and not splitting CJK numerals)
                        // NB otherwise it move lastchar(s) to next line to keep $c company = Oidashi, which is done below in standard way
                        // add character onto this line
                        $currContent .= $c;
                        $contentWidth += $cw;
                        $Oikomi = true; // Set flag
                        continue;
                    }
                }
                /* -- END CJK-FONTS -- */
                /* -- HYPHENATION -- */

                // AUTOMATIC HYPHENATION
                // 2) Automatic hyphen in current word (does not cross tags)
                if (isset($this->textparam['hyphens']) && $this->textparam['hyphens'] == 1) {
                    $currWord = '';
                    // Look back and ahead to get current word
                    for ($ac = $charctr - 1; $ac >= 0; $ac--) {
                        if ($this->usingCoreFont) {
                            $addc = substr($currContent, $ac, 1);
                        } else {
                            $addc = mb_substr($currContent, $ac, 1, $this->mb_enc);
                        }
                        if ($addc == ' ') {
                            break;
                        }
                        $currWord = $addc . $currWord;
                    }
                    $start = $ac + 1;
                    for ($ac = $i; $ac < ($clen - 1); $ac++) {
                        if ($this->usingCoreFont) {
                            $addc = substr($s, $ac, 1);
                        } else {
                            $addc = mb_substr($s, $ac, 1, $this->mb_enc);
                        }
                        if ($addc == ' ') {
                            break;
                        }
                        $currWord .= $addc;
                    }
                    $ptr = $this->hyphenator->hyphenateWord($currWord, $charctr - $start);
                    if ($ptr > -1) {
                        $breakfound = [$contentctr, $start + $ptr, $contentctr, $start + $ptr, 'hyphen'];
                    }
                }
                /* -- END HYPHENATION -- */

                // Search backwards to find first line-break opportunity
                while ($breakfound == false && $prevchar !== false) {
                    $cutcontentctr = $contentctr;
                    $cutcharctr = $charctr;
                    $prevchar = $this->_moveToPrevChar($contentctr, $charctr, $content);
                    /////////////////////
                    // 3) Break at SPACE
                    /////////////////////
                    if ($prevchar == ' ') {
                        $breakfound = [$contentctr, $charctr, $cutcontentctr, $cutcharctr, 'discard'];
                    } /////////////////////
                    // 4) Break at U+200B in current word (Khmer, Lao & Thai Invisible word boundary, and Tibetan)
                    /////////////////////
                    elseif ($prevchar == "\xe2\x80\x8b") { // U+200B Zero-width Word Break
                        $breakfound = [$contentctr, $charctr, $cutcontentctr, $cutcharctr, 'discard'];
                    } /////////////////////
                    // 5) Break at Hard HYPHEN '-' or U+2010
                    /////////////////////
                    elseif (isset($this->textparam['hyphens']) && $this->textparam['hyphens'] != 2 && ($prevchar == '-' || $prevchar == "\xe2\x80\x90")) {
                        // Don't break a URL
                        // Look back to get first part of current word
                        $checkw = '';
                        for ($ac = $charctr - 1; $ac >= 0; $ac--) {
                            if ($this->usingCoreFont) {
                                $addc = substr($currContent, $ac, 1);
                            } else {
                                $addc = mb_substr($currContent, $ac, 1, $this->mb_enc);
                            }
                            if ($addc == ' ') {
                                break;
                            }
                            $checkw = $addc . $checkw;
                        }
                        // Don't break if HyphenMinus AND (a URL or before a numeral or before a >)
                        if ((!preg_match('/(http:|ftp:|https:|www\.)/', $checkw) && $checkchar != '>' && !preg_match('/[0-9]/', $checkchar)) || $prevchar == "\xe2\x80\x90") {
                            $breakfound = [$cutcontentctr, $cutcharctr, $cutcontentctr, $cutcharctr, 'cut'];
                        }
                    } /////////////////////
                    // 6) Break at Soft HYPHEN (replace with hard hyphen)
                    /////////////////////
                    elseif (isset($this->textparam['hyphens']) && $this->textparam['hyphens'] != 2 && !$this->usingCoreFont && $prevchar == "\xc2\xad") {
                        $breakfound = [$cutcontentctr, $cutcharctr, $cutcontentctr, $cutcharctr, 'cut'];
                        $content[$contentctr] = mb_substr($content[$contentctr], 0, $charctr, $this->mb_enc) . '-' . mb_substr($content[$contentctr], $charctr + 1, mb_strlen($content[$contentctr]), $this->mb_enc);
                        if (!empty($cOTLdata[$contentctr])) {
                            $cOTLdata[$contentctr]['char_data'][$charctr] = ['bidi_class' => 9, 'uni' => 45];
                            $cOTLdata[$contentctr]['group'][$charctr] = 'C';
                        }
                    } elseif (isset($this->textparam['hyphens']) && $this->textparam['hyphens'] != 2 && $this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats' && $prevchar == chr(173)) {
                        $breakfound = [$cutcontentctr, $cutcharctr, $cutcontentctr, $cutcharctr, 'cut'];
                        $content[$contentctr] = substr($content[$contentctr], 0, $charctr) . '-' . substr($content[$contentctr], $charctr + 1);
                    } /* -- CJK-FONTS -- */
                    /////////////////////
                    // 7) Break at CJK characters (unless forbidden characters to end or start line)
                    // CJK Avoiding line break in the middle of numerals
                    /////////////////////
                    elseif (!$this->usingCoreFont && $this->checkCJK && preg_match("/[" . $this->pregCJKchars . "]/u", $checkchar) &&
                        !preg_match("/[" . $this->CJKfollowing . "]/u", $checkchar) && !preg_match("/[" . $this->CJKleading . "]/u", $prevchar) &&
                        !(preg_match("/[0-9\x{ff10}-\x{ff19}]/u", $prevchar) && preg_match("/[0-9\x{ff10}-\x{ff19}]/u", $checkchar))) {
                        $breakfound = [$cutcontentctr, $cutcharctr, $cutcontentctr, $cutcharctr, 'cut'];
                    }
                    /* -- END CJK-FONTS -- */
                    /////////////////////
                    // 8) Break at OBJECT (Break before all objects here - selected objects are moved forward to next line below e.g. dottab)
                    /////////////////////
                    if (isset($this->objectbuffer[$contentctr])) {
                        $breakfound = [$cutcontentctr, $cutcharctr, $cutcontentctr, $cutcharctr, 'cut'];
                    }


                    $checkchar = $prevchar;
                }

                // If a line-break opportunity found:
                if (is_array($breakfound)) {
                    $contentctr = $breakfound[0];
                    $charctr = $breakfound[1];
                    $cutcontentctr = $breakfound[2];
                    $cutcharctr = $breakfound[3];
                    $type = $breakfound[4];
                    // Cache chunks which are already processed, but now need to be passed on to the new line
                    for ($ix = count($content) - 1; $ix > $cutcontentctr; $ix--) {
                        // save and crop off any subsequent chunks
                        /* -- OTL -- */
                        if (!empty($sOTLdata)) {
                            $tmpOTL = array_pop($cOTLdata);
                            $savedPreOTLdata[] = $tmpOTL;
                        }
                        /* -- END OTL -- */
                        $savedPreContent[] = array_pop($content);
                        $savedPreContentB[] = array_pop($contentB);
                        $savedPreFont[] = array_pop($font);
                    }

                    // Next cache the part which will start the next line
                    if ($this->usingCoreFont) {
                        $savedPreContent[] = substr($content[$cutcontentctr], $cutcharctr);
                    } else {
                        $savedPreContent[] = mb_substr($content[$cutcontentctr], $cutcharctr, mb_strlen($content[$cutcontentctr]), $this->mb_enc);
                    }
                    $savedPreContentB[] = preg_replace('/L/', '', $contentB[$cutcontentctr]);
                    $savedPreFont[] = $font[$cutcontentctr];
                    /* -- OTL -- */
                    if (!empty($sOTLdata)) {
                        $savedPreOTLdata[] = $this->otl->splitOTLdata($cOTLdata[$cutcontentctr], $cutcharctr, $cutcharctr);
                    }
                    /* -- END OTL -- */


                    // Finally adjust the Current content which ends this line
                    if ($cutcharctr == 0 && $type == 'discard') {
                        array_pop($content);
                        array_pop($contentB);
                        array_pop($font);
                        array_pop($cOTLdata);
                    }

                    $currContent = & $content[count($content) - 1];
                    if ($this->usingCoreFont) {
                        $currContent = substr($currContent, 0, $charctr);
                    } else {
                        $currContent = mb_substr($currContent, 0, $charctr, $this->mb_enc);
                    }

                    if (!empty($sOTLdata)) {
                        $savedPreOTLdata[] = $this->otl->splitOTLdata($cOTLdata[(count($cOTLdata) - 1)], mb_strlen($currContent, $this->mb_enc));
                    }

                    if (strpos($contentB[(count($contentB) - 1)], 'R') !== false) {   // ???
                        $contentB[count($content) - 1] = preg_replace('/R/', '', $contentB[count($content) - 1]); // ???
                    }

                    if ($type == 'hyphen') {
                        $currContent .= '-';
                        if (!empty($cOTLdata[(count($cOTLdata) - 1)])) {
                            $cOTLdata[(count($cOTLdata) - 1)]['char_data'][] = ['bidi_class' => 9, 'uni' => 45];
                            $cOTLdata[(count($cOTLdata) - 1)]['group'] .= 'C';
                        }
                    }

                    $savedContent = '';
                    $savedContentB = '';
                    $savedFont = [];
                    $savedOTLdata = [];
                }
                // If no line-break opportunity found - split at current position
                // or - Next character ($c) is suitable to add as overhanging or squeezed punctuation, or Oikomi, as set above by:
                // 1) CJK Overflowing a) punctuation or b) Oikomi
                // in which case $breakfound==1 and NOT array

                if (!is_array($breakfound)) {
                    $savedFont = $this->saveFont();
                    if (!empty($sOTLdata)) {
                        $savedOTLdata = $this->otl->splitOTLdata($cOTLdata[(count($cOTLdata) - 1)], mb_strlen($currContent, $this->mb_enc));
                    }
                }

                if ($content[count($content) - 1] == '' && !isset($this->objectbuffer[count($content) - 1])) {
                    array_pop($content);
                    array_pop($contentB);
                    array_pop($font);
                    array_pop($cOTLdata);
                    $currContent = & $content[count($content) - 1];
                }

                // Right Trim current content - including CJK space, and for OTLdata
                // incl. CJK - strip CJK space at end of line &#x3000; = \xe3\x80\x80 = CJK space
                $currContent = rtrim($currContent);
                if ($this->checkCJK) {
                    $currContent = preg_replace("/\xe3\x80\x80$/", '', $currContent);
                } // *CJK-FONTS*
                /* -- OTL -- */
                if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                    $this->otl->trimOTLdata($cOTLdata[count($cOTLdata) - 1], false, true); // NB also does U+3000
                }
                /* -- END OTL -- */


                // Selected OBJECTS are moved forward to next line, unless they come before a space or U+200B (type='discard')
                if (isset($this->objectbuffer[(count($content) - 1)]) && (!isset($type) || $type != 'discard')) {
                    $objtype = $this->objectbuffer[(count($content) - 1)]['type'];
                    if ($objtype == 'dottab' || $objtype == 'bookmark' || $objtype == 'indexentry' || $objtype == 'toc' || $objtype == 'annot') {
                        $savedObj = array_pop($this->objectbuffer);
                    }
                }


                // Decimal alignment (cancel if wraps to > 1 line)
                if ($is_table && substr($align, 0, 1) == 'D') {
                    $align = substr($align, 2, 1);
                }

                $lineBox = [];

                $this->_setInlineBlockHeights($lineBox, $stackHeight, $content, $font, $is_table);

                // update $contentWidth since it has changed with cropping
                $contentWidth = 0;

                $inclCursive = false;
                foreach ($content as $k => $chunk) {
                    if (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]) {
                        // LIST MARKERS
                        if ($this->objectbuffer[$k]['type'] == 'image' && isset($this->objectbuffer[$k]['listmarker']) && $this->objectbuffer[$k]['listmarker']) {
                            if ($this->objectbuffer[$k]['listmarkerposition'] != 'outside') {
                                $contentWidth += $this->objectbuffer[$k]['OUTER-WIDTH'] * Mpdf::SCALE;
                            }
                        } else {
                            $contentWidth += $this->objectbuffer[$k]['OUTER-WIDTH'] * Mpdf::SCALE;
                        }
                    } elseif (!isset($this->objectbuffer[$k]) || (isset($this->objectbuffer[$k]) && !$this->objectbuffer[$k])) {
                        $this->restoreFont($font[$k], false);
                        if ($this->checkCJK && $k == count($content) - 1 && $CJKoverflow && $align == 'J' && $this->allowCJKoverflow && $this->CJKforceend) {
                            // force-end overhang
                            $hanger = mb_substr($chunk, mb_strlen($chunk, $this->mb_enc) - 1, 1, $this->mb_enc);
                            // Probably ought to do something with char_data and GPOS in cOTLdata...
                            $content[$k] = $chunk = mb_substr($chunk, 0, mb_strlen($chunk, $this->mb_enc) - 1, $this->mb_enc);
                        }

                        // Soft Hyphens chr(173) + Replace NBSP with SPACE + Set inclcursive if includes CURSIVE TEXT
                        if (!$this->usingCoreFont) {
                            /* -- OTL -- */
                            if ((isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) || !empty($sOTLdata)) {
                                $this->otl->removeChar($chunk, $cOTLdata[$k], "\xc2\xad");
                                $this->otl->replaceSpace($chunk, $cOTLdata[$k]); // NBSP -> space
                                if (preg_match("/([" . $this->pregCURSchars . "])/u", $chunk)) {
                                    $inclCursive = true;
                                }
                                $content[$k] = $chunk;
                            } /* -- END OTL -- */ else {  // *OTL*
                                $content[$k] = $chunk = str_replace("\xc2\xad", '', $chunk);
                                $content[$k] = $chunk = str_replace(chr(194) . chr(160), chr(32), $chunk);
                            } // *OTL*
                        } elseif ($this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats') {
                            $content[$k] = $chunk = str_replace(chr(173), '', $chunk);
                            $content[$k] = $chunk = str_replace(chr(160), chr(32), $chunk);
                        }

                        $contentWidth += $this->GetStringWidth($chunk, true, (isset($cOTLdata[$k]) ? $cOTLdata[$k] : false), $this->textvar) * Mpdf::SCALE;  // mPDF 5.7.1
                        if (!empty($this->spanborddet)) {
                            if (isset($this->spanborddet['L']['w']) && strpos($contentB[$k], 'L') !== false) {
                                $contentWidth += $this->spanborddet['L']['w'] * Mpdf::SCALE;
                            }
                            if (isset($this->spanborddet['R']['w']) && strpos($contentB[$k], 'R') !== false) {
                                $contentWidth += $this->spanborddet['R']['w'] * Mpdf::SCALE;
                            }
                        }
                    }
                }

                $lastfontreqstyle = (isset($font[count($font) - 1]['ReqFontStyle']) ? $font[count($font) - 1]['ReqFontStyle'] : '');
                $lastfontstyle = (isset($font[count($font) - 1]['style']) ? $font[count($font) - 1]['style'] : '');
                if ($blockdir == 'ltr' && strpos($lastfontreqstyle, "I") !== false && strpos($lastfontstyle, "I") === false) { // Artificial italic
                    $lastitalic = $this->FontSize * 0.15 * Mpdf::SCALE;
                } else {
                    $lastitalic = 0;
                }




                // NOW FORMAT THE LINE TO OUTPUT
                if (!$table_draft) {
                    // DIRECTIONALITY RTL
                    $chunkorder = range(0, count($content) - 1); // mPDF 5.7
                    /* -- OTL -- */
                    // mPDF 6
                    if ($blockdir == 'rtl' || $this->biDirectional) {
                        $this->otl->bidiReorder($chunkorder, $content, $cOTLdata, $blockdir);
                        // From this point on, $content and $cOTLdata may contain more elements (and re-ordered) compared to
                        // $this->objectbuffer and $font ($chunkorder contains the mapping)
                    }

                    /* -- END OTL -- */
                    // Remove any XAdvance from OTL data at end of line
                    foreach ($chunkorder as $aord => $k) {
                        if (count($cOTLdata)) {
                            $this->restoreFont($font[$k], false);
                            // ...WriteFlowingBlock...
                            if ($aord == count($chunkorder) - 1 && isset($cOTLdata[$aord]['group'])) { // Last chunk on line
                                $nGPOS = strlen($cOTLdata[$aord]['group']) - 1; // Last character
                                if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL']) || isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'])) {
                                    if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'])) {
                                        $w = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] * 1000 / $this->CurrentFont['unitsPerEm'];
                                    } else {
                                        $w = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] * 1000 / $this->CurrentFont['unitsPerEm'];
                                    }
                                    $w *= ($this->FontSize / 1000);
                                    $contentWidth -= $w * Mpdf::SCALE;
                                    $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] = 0;
                                    $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] = 0;
                                }

                                // If last character has an XPlacement set, adjust width calculation, and add to XAdvance to account for it
                                if (isset($cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'])) {
                                    $w = -$cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'] * 1000 / $this->CurrentFont['unitsPerEm'];
                                    $w *= ($this->FontSize / 1000);
                                    $contentWidth -= $w * Mpdf::SCALE;
                                    $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceL'] = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'];
                                    $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XAdvanceR'] = $cOTLdata[$aord]['GPOSinfo'][$nGPOS]['XPlacement'];
                                }
                            }
                        }
                    }

                    // JUSTIFICATION J
                    $jcharspacing = 0;
                    $jws = 0;
                    $nb_carac = 0;
                    $nb_spaces = 0;
                    $jkashida = 0;
                    // if it's justified, we need to find the char/word spacing (or if hanger $this->CJKforceend)
                    if (($align == 'J' && !$CJKoverflow) || (($contentWidth + $lastitalic > $maxWidth - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) ) + 0.001) && (!$CJKoverflow || ($CJKoverflow && !$this->allowCJKoverflow))) || $CJKoverflow && $align == 'J' && $this->allowCJKoverflow && $hanger && $this->CJKforceend) {   // 0.001 is to correct for deviations converting mm=>pts
                        // JUSTIFY J (Use character spacing)
                        // WORD SPACING
                        // mPDF 5.7
                        foreach ($chunkorder as $aord => $k) {
                            $chunk = isset($content[$aord]) ? $content[$aord] : '';
                            if (!isset($this->objectbuffer[$k]) || (isset($this->objectbuffer[$k]) && !$this->objectbuffer[$k])) {
                                $nb_carac += mb_strlen($chunk, $this->mb_enc);
                                $nb_spaces += mb_substr_count($chunk, ' ', $this->mb_enc);
                                // Use GPOS OTL
                                if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF)) {
                                    if (isset($cOTLdata[$aord]['group']) && $cOTLdata[$aord]['group']) {
                                        $nb_carac -= substr_count($cOTLdata[$aord]['group'], 'M');
                                    }
                                }
                            } else {
                                $nb_carac ++;
                            } // mPDF 6 allow spacing for inline object
                        }
                        // GetJSpacing adds kashida spacing to GPOSinfo if appropriate for Font
                        list($jcharspacing, $jws, $jkashida) = $this->GetJspacing($nb_carac, $nb_spaces, ($maxWidth - $lastitalic - $contentWidth - $WidthCorrection - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) )), $inclCursive, $cOTLdata);
                    }

                    // WORD SPACING
                    $empty = $maxWidth - $lastitalic - $WidthCorrection - $contentWidth - (($this->cMarginL + $this->cMarginR) * Mpdf::SCALE) - ($paddingL + $paddingR + (($fpaddingL + $fpaddingR) * Mpdf::SCALE) );

                    $empty -= ($jcharspacing * ($nb_carac - 1)); // mPDF 6 nb_carac MINUS 1
                    $empty -= ($jws * $nb_spaces);
                    $empty -= ($jkashida);
                    $empty /= Mpdf::SCALE;

                    $b = ''; // do not use borders
                    // Get PAGEBREAK TO TEST for height including the top border/padding
                    $check_h = max($this->divheight, $stackHeight);
                    if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($this->blklvl > 0) && ($lineCount == 1) && (!$is_table)) {
                        $check_h += ($this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['border_top']['w']);
                    }

                    if ($this->ColActive && $check_h > ($this->PageBreakTrigger - $this->y0)) {
                        $this->SetCol($this->NbCol - 1);
                    }

                    // PAGEBREAK
                    // 'If' below used in order to fix "first-line of other page with justify on" bug
                    if (!$is_table && ($this->y + $check_h) > $this->PageBreakTrigger and ! $this->InFooter and $this->AcceptPageBreak()) {
                        $bak_x = $this->x; // Current X position
                        // WORD SPACING
                        $ws = $this->ws; // Word Spacing
                        $charspacing = $this->charspacing; // Character Spacing
                        $this->ResetSpacing();

                        $this->AddPage($this->CurOrientation);

                        $this->x = $bak_x;
                        // Added to correct for OddEven Margins
                        $currentx += $this->MarginCorrection;
                        $this->x += $this->MarginCorrection;

                        // WORD SPACING
                        $this->SetSpacing($charspacing, $ws);
                    }

                    if ($this->kwt && !$is_table) { // mPDF 5.7+
                        $this->printkwtbuffer();
                        $this->kwt = false;
                    }


                    /* -- COLUMNS -- */
                    // COLS
                    // COLUMN CHANGE
                    if ($this->CurrCol != $oldcolumn) {
                        $currentx += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
                        $this->x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
                        $oldcolumn = $this->CurrCol;
                    }

                    if ($this->ColActive && !$is_table) {
                        $this->breakpoints[$this->CurrCol][] = $this->y;
                    } // *COLUMNS*
                    /* -- END COLUMNS -- */

                    // TOP MARGIN
                    if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($this->blk[$this->blklvl]['margin_top']) && ($lineCount == 1) && (!$is_table)) {
                        $this->DivLn($this->blk[$this->blklvl]['margin_top'], $this->blklvl - 1, true, $this->blk[$this->blklvl]['margin_collapse']);
                        if ($this->ColActive) {
                            $this->breakpoints[$this->CurrCol][] = $this->y;
                        } // *COLUMNS*
                    }


                    // Update y0 for top of block (used to paint border)
                    if (($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 1) && (!$is_table)) {
                        $this->blk[$this->blklvl]['y0'] = $this->y;
                        $this->blk[$this->blklvl]['startpage'] = $this->page;
                        if ($this->blk[$this->blklvl]['float']) {
                            $this->blk[$this->blklvl]['float_start_y'] = $this->y;
                        }
                    }

                    // TOP PADDING and BORDER spacing/fill
                    if (($newblock) && ($blockstate == 1 || $blockstate == 3) && (($this->blk[$this->blklvl]['padding_top']) || ($this->blk[$this->blklvl]['border_top'])) && ($lineCount == 1) && (!$is_table)) {
                        // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
                        $this->DivLn($this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'], -3, true, false, 1);
                        if ($this->ColActive) {
                            $this->breakpoints[$this->CurrCol][] = $this->y;
                        } // *COLUMNS*
                    }

                    $arraysize = count($chunkorder);

                    $margins = ($this->cMarginL + $this->cMarginR) + ($ipaddingL + $ipaddingR + $fpaddingR + $fpaddingR );

                    // PAINT BACKGROUND FOR THIS LINE
                    if (!$is_table) {
                        $this->DivLn($stackHeight, $this->blklvl, false);
                    } // false -> don't advance y

                    $this->x = $currentx + $this->cMarginL + $ipaddingL + $fpaddingL;
                    if ($align == 'R') {
                        $this->x += $empty;
                    } elseif ($align == 'C') {
                        $this->x += ($empty / 2);
                    }

                    // Paragraph INDENT
                    if (isset($this->blk[$this->blklvl]['text_indent']) && ($newblock) && ($blockstate == 1 || $blockstate == 3) && ($lineCount == 1) && (!$is_table) && ($blockdir != 'rtl') && ($align != 'C')) {
                        $ti = $this->sizeConverter->convert($this->blk[$this->blklvl]['text_indent'], $this->blk[$this->blklvl]['inner_width'], $this->blk[$this->blklvl]['InlineProperties']['size'], false);  // mPDF 5.7.4
                        $this->x += $ti;
                    }

                    // BIDI magic_reverse moved upwards from here
                    foreach ($chunkorder as $aord => $k) { // mPDF 5.7

                        $chunk = isset($content[$aord]) ? $content[$aord] : '';

                        if (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]) {
                            $xadj = $this->x - $this->objectbuffer[$k]['OUTER-X'];
                            $this->objectbuffer[$k]['OUTER-X'] += $xadj;
                            $this->objectbuffer[$k]['BORDER-X'] += $xadj;
                            $this->objectbuffer[$k]['INNER-X'] += $xadj;

                            if ($this->objectbuffer[$k]['type'] == 'listmarker') {
                                $this->objectbuffer[$k]['lineBox'] = $lineBox[-1]; // Block element details for glyph-origin
                            }
                            $yadj = $this->y - $this->objectbuffer[$k]['OUTER-Y'];
                            if ($this->objectbuffer[$k]['type'] == 'dottab') { // mPDF 6 DOTTAB
                                $this->objectbuffer[$k]['lineBox'] = $lineBox[$k]; // element details for glyph-origin
                            }
                            if ($this->objectbuffer[$k]['type'] != 'dottab') { // mPDF 6 DOTTAB
                                $yadj += $lineBox[$k]['top'];
                            }
                            $this->objectbuffer[$k]['OUTER-Y'] += $yadj;
                            $this->objectbuffer[$k]['BORDER-Y'] += $yadj;
                            $this->objectbuffer[$k]['INNER-Y'] += $yadj;
                        }

                        $this->restoreFont($font[$k]);  // mPDF 5.7

                        $this->SetSpacing(($this->fixedlSpacing * Mpdf::SCALE) + $jcharspacing, ($this->fixedlSpacing + $this->minwSpacing) * Mpdf::SCALE + $jws);
                        // Now unset these values so they don't influence GetStringwidth below or in fn. Cell
                        $this->fixedlSpacing = false;
                        $this->minwSpacing = 0;

                        $save_vis = $this->visibility;
                        if (isset($this->textparam['visibility']) && $this->textparam['visibility'] && $this->textparam['visibility'] != $this->visibility) {
                            $this->SetVisibility($this->textparam['visibility']);
                        }
                        // *********** SPAN BACKGROUND COLOR ***************** //
                        if ($this->spanbgcolor) {
                            $cor = $this->spanbgcolorarray;
                            $this->SetFColor($cor);
                            $save_fill = $fill;
                            $spanfill = 1;
                            $fill = 1;
                        }
                        if (!empty($this->spanborddet)) {
                            if (strpos($contentB[$k], 'L') !== false) {
                                $this->x += (isset($this->spanborddet['L']['w']) ? $this->spanborddet['L']['w'] : 0);
                            }
                            if (strpos($contentB[$k], 'L') === false) {
                                $this->spanborddet['L']['s'] = $this->spanborddet['L']['w'] = 0;
                            }
                            if (strpos($contentB[$k], 'R') === false) {
                                $this->spanborddet['R']['s'] = $this->spanborddet['R']['w'] = 0;
                            }
                        }

                        // WORD SPACING
                        // StringWidth this time includes any kashida spacing
                        $stringWidth = $this->GetStringWidth($chunk, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, true);

                        $nch = mb_strlen($chunk, $this->mb_enc);
                        // Use GPOS OTL
                        if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0xFF)) {
                            if (isset($cOTLdata[$aord]['group']) && $cOTLdata[$aord]['group']) {
                                $nch -= substr_count($cOTLdata[$aord]['group'], 'M');
                            }
                        }
                        $stringWidth += ( $this->charspacing * $nch / Mpdf::SCALE );

                        $stringWidth += ( $this->ws * mb_substr_count($chunk, ' ', $this->mb_enc) / Mpdf::SCALE );

                        if (isset($this->objectbuffer[$k])) {
                            // LIST MARKERS // mPDF 6  Lists
                            if ($this->objectbuffer[$k]['type'] == 'image' && isset($this->objectbuffer[$k]['listmarker']) && $this->objectbuffer[$k]['listmarker'] && $this->objectbuffer[$k]['listmarkerposition'] == 'outside') {
                                $stringWidth = 0;
                            } else {
                                $stringWidth = $this->objectbuffer[$k]['OUTER-WIDTH'];
                            }
                        }

                        if ($stringWidth == 0) {
                            $stringWidth = 0.000001;
                        }

                        if ($aord == $arraysize - 1) {
                            $stringWidth -= ( $this->charspacing / Mpdf::SCALE );
                            if ($this->checkCJK && $CJKoverflow && $align == 'J' && $this->allowCJKoverflow && $hanger && $this->CJKforceend) {
                                // force-end overhang
                                $this->Cell($stringWidth, $stackHeight, $chunk, '', 0, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false));
                                $this->Cell($this->GetStringWidth($hanger), $stackHeight, $hanger, '', 1, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false));
                            } else {
                                $this->Cell($stringWidth, $stackHeight, $chunk, '', 1, '', $fill, $this->HREF, $currentx, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false)); // mono-style line or last part (skips line)
                            }
                        } else {
                            $this->Cell($stringWidth, $stackHeight, $chunk, '', 0, '', $fill, $this->HREF, 0, 0, 0, 'M', $fill, true, (isset($cOTLdata[$aord]) ? $cOTLdata[$aord] : false), $this->textvar, (isset($lineBox[$k]) ? $lineBox[$k] : false)); // first or middle part
                        }

                        if (!empty($this->spanborddet)) {
                            if (strpos($contentB[$k], 'R') !== false && $aord != $arraysize - 1) {
                                $this->x += $this->spanborddet['R']['w'];
                            }
                        }
                        // *********** SPAN BACKGROUND COLOR OFF - RESET BLOCK BGCOLOR ***************** //
                        if (isset($spanfill) && $spanfill) {
                            $fill = $save_fill;
                            $spanfill = 0;
                            if ($fill) {
                                $this->SetFColor($bcor);
                            }
                        }
                        if (isset($this->textparam['visibility']) && $this->textparam['visibility'] && $this->visibility != $save_vis) {
                            $this->SetVisibility($save_vis);
                        }
                    }
                } elseif ($table_draft) {
                    $this->y += $stackHeight;
                }

                if (!$is_table) {
                    $this->maxPosR = max($this->maxPosR, ($this->w - $this->rMargin - $this->blk[$this->blklvl]['outer_right_margin']));
                    $this->maxPosL = min($this->maxPosL, ($this->lMargin + $this->blk[$this->blklvl]['outer_left_margin']));
                }

                // move on to the next line, reset variables, tack on saved content and current char

                if (!$table_draft) {
                    $this->printobjectbuffer($is_table, $blockdir);
                }
                $this->objectbuffer = [];


                /* -- CSS-IMAGE-FLOAT -- */
                // Update values if set to skipline
                if ($this->floatmargins) {
                    $this->_advanceFloatMargins();
                }
                /* -- END CSS-IMAGE-FLOAT -- */

                // Reset lineheight
                $stackHeight = $this->divheight;
                $valign = 'M';

                $font = [];
                $content = [];
                $contentB = [];
                $cOTLdata = []; // mPDF 5.7.1
                $contentWidth = 0;
                if (!empty($savedObj)) {
                    $this->objectbuffer[] = $savedObj;
                    $font[] = $savedFont;
                    $content[] = '';
                    $contentB[] = '';
                    $cOTLdata[] = []; // mPDF 5.7.1
                    $contentWidth += $savedObj['OUTER-WIDTH'] * Mpdf::SCALE;
                }
                if (count($savedPreContent) > 0) {
                    for ($ix = count($savedPreContent) - 1; $ix >= 0; $ix--) {
                        $font[] = $savedPreFont[$ix];
                        $content[] = $savedPreContent[$ix];
                        $contentB[] = $savedPreContentB[$ix];
                        if (!empty($sOTLdata)) {
                            $cOTLdata[] = $savedPreOTLdata[$ix];
                        }
                        $this->restoreFont($savedPreFont[$ix]);
                        $lbw = $rbw = 0; // Border widths
                        if (!empty($this->spanborddet)) {
                            $lbw = (isset($this->spanborddet['L']['w']) ? $this->spanborddet['L']['w'] : 0);
                            $rbw = (isset($this->spanborddet['R']['w']) ? $this->spanborddet['R']['w'] : 0);
                        }
                        if ($ix > 0) {
                            $contentWidth += $this->GetStringWidth($savedPreContent[$ix], true, (isset($savedPreOTLdata[$ix]) ? $savedPreOTLdata[$ix] : false), $this->textvar) * Mpdf::SCALE; // mPDF 5.7.1
                            if (strpos($savedPreContentB[$ix], 'L') !== false) {
                                $contentWidth += $lbw;
                            }
                            if (strpos($savedPreContentB[$ix], 'R') !== false) {
                                $contentWidth += $rbw;
                            }
                        }
                    }
                    $savedPreContent = [];
                    $savedPreContentB = [];
                    $savedPreOTLdata = []; // mPDF 5.7.1
                    $savedPreFont = [];
                    $content[(count($content) - 1)] .= $c;
                } else {
                    $font[] = $savedFont;
                    $content[] = $savedContent . $c;
                    $contentB[] = $savedContentB;
                    $cOTLdata[] = $savedOTLdata; // mPDF 5.7.1
                }

                $currContent = & $content[(count($content) - 1)];
                $this->restoreFont($font[(count($font) - 1)]); // mPDF 6.0

                /* -- CJK-FONTS -- */
                // CJK - strip CJK space at start of line
                // &#x3000; = \xe3\x80\x80 = CJK space
                if ($this->checkCJK && $currContent == "\xe3\x80\x80") {
                    $currContent = '';
                    if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
                        $this->otl->trimOTLdata($cOTLdata[count($cOTLdata) - 1], true, false); // left trim U+3000
                    }
                }
                /* -- END CJK-FONTS -- */

                $lbw = $rbw = 0; // Border widths
                if (!empty($this->spanborddet)) {
                    $lbw = (isset($this->spanborddet['L']['w']) ? $this->spanborddet['L']['w'] : 0);
                    $rbw = (isset($this->spanborddet['R']['w']) ? $this->spanborddet['R']['w'] : 0);
                }

                $contentWidth += $this->GetStringWidth($currContent, false, (isset($cOTLdata[(count($cOTLdata) - 1)]) ? $cOTLdata[(count($cOTLdata) - 1)] : false), $this->textvar) * Mpdf::SCALE; // mPDF 5.7.1
                if (strpos($savedContentB, 'L') !== false) {
                    $contentWidth += $lbw;
                }
                $CJKoverflow = false;
                $hanger = '';
            } // another character will fit, so add it on
            else {
                $contentWidth += $cw;
                $currContent .= $c;
            }
        }

        unset($content);
        unset($contentB);
    }

    // ----------------------END OF FLOWING BLOCK------------------------------------//


    /* -- END HTML-CSS -- */

    public function SetTextOutline($params = [])
    {
        if (isset($params['outline-s']) && $params['outline-s']) {
            $this->SetLineWidth($params['outline-WIDTH']);
            $this->SetDColor($params['outline-COLOR']);
            $tr = ('2 Tr');
            if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['TextRendering']) && $this->pageoutput[$this->page]['TextRendering'] != $tr) || !isset($this->pageoutput[$this->page]['TextRendering']))) {
                $this->_out($tr);
            }
            $this->pageoutput[$this->page]['TextRendering'] = $tr;
        } else { // Now resets all values
            $this->SetLineWidth(0.2);
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $this->_SetTextRendering(0);
            $tr = ('0 Tr');
            if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['TextRendering']) && $this->pageoutput[$this->page]['TextRendering'] != $tr) || !isset($this->pageoutput[$this->page]['TextRendering']))) {
                $this->_out($tr);
            }
            $this->pageoutput[$this->page]['TextRendering'] = $tr;
        }
    }

    public function Image($file, $x, $y, $w = 0, $h = 0, $type = '', $link = '', $paint = true, $constrain = true, $watermark = false, $shownoimg = true, $allowvector = true)
    {
        $orig_srcpath = $file;
        $this->GetFullPath($file);

        $info = $this->imageProcessor->getImage($file, true, $allowvector, $orig_srcpath);
        if (!$info && $paint) {
            $info = $this->imageProcessor->getImage($this->noImageFile);
            if ($info) {
                $file = $this->noImageFile;
                $w = ($info['w'] * (25.4 / $this->dpi));  // 14 x 16px
                $h = ($info['h'] * (25.4 / $this->dpi));  // 14 x 16px
            }
        }
        if (!$info) {
            return false;
        }
        // Automatic width and height calculation if needed
        if ($w == 0 and $h == 0) {
            /* -- IMAGES-WMF -- */
            if ($info['type'] == 'wmf') {
                // WMF units are twips (1/20pt)
                // divide by 20 to get points
                // divide by k to get user units
                $w = abs($info['w']) / (20 * Mpdf::SCALE);
                $h = abs($info['h']) / (20 * Mpdf::SCALE);
            } else {            /* -- END IMAGES-WMF -- */
                if ($info['type'] == 'svg') {
                    // returned SVG units are pts
                    // divide by k to get user units (mm)
                    $w = abs($info['w']) / Mpdf::SCALE;
                    $h = abs($info['h']) / Mpdf::SCALE;
                } else {
                    // Put image at default image dpi
                    $w = ($info['w'] / Mpdf::SCALE) * (72 / $this->img_dpi);
                    $h = ($info['h'] / Mpdf::SCALE) * (72 / $this->img_dpi);
                }
            }
        }
        if ($w == 0) {
            $w = abs($h * $info['w'] / $info['h']);
        }
        if ($h == 0) {
            $h = abs($w * $info['h'] / $info['w']);
        }

        /* -- WATERMARK -- */
        if ($watermark) {
            $maxw = $this->w;
            $maxh = $this->h;
            // Size = D PF or array
            if (is_array($this->watermark_size)) {
                $w = $this->watermark_size[0];
                $h = $this->watermark_size[1];
            } elseif (!is_string($this->watermark_size)) {
                $maxw -= $this->watermark_size * 2;
                $maxh -= $this->watermark_size * 2;
                $w = $maxw;
                $h = abs($w * $info['h'] / $info['w']);
                if ($h > $maxh) {
                    $h = $maxh;
                    $w = abs($h * $info['w'] / $info['h']);
                }
            } elseif ($this->watermark_size == 'F') {
                if ($this->ColActive) {
                    $maxw = $this->w - ($this->DeflMargin + $this->DefrMargin);
                } else {
                    $maxw = $this->pgwidth;
                }
                $maxh = $this->h - ($this->tMargin + $this->bMargin);
                $w = $maxw;
                $h = abs($w * $info['h'] / $info['w']);
                if ($h > $maxh) {
                    $h = $maxh;
                    $w = abs($h * $info['w'] / $info['h']);
                }
            } elseif ($this->watermark_size == 'P') { // Default P
                $w = $maxw;
                $h = abs($w * $info['h'] / $info['w']);
                if ($h > $maxh) {
                    $h = $maxh;
                    $w = abs($h * $info['w'] / $info['h']);
                }
            }
            // Automatically resize to maximum dimensions of page if too large
            if ($w > $maxw) {
                $w = $maxw;
                $h = abs($w * $info['h'] / $info['w']);
            }
            if ($h > $maxh) {
                $h = $maxh;
                $w = abs($h * $info['w'] / $info['h']);
            }
            // Position
            if (is_array($this->watermark_pos)) {
                $x = $this->watermark_pos[0];
                $y = $this->watermark_pos[1];
            } elseif ($this->watermark_pos == 'F') { // centred on printable area
                if ($this->ColActive) { // *COLUMNS*
                    if (($this->mirrorMargins) && (($this->page) % 2 == 0)) {
                        $xadj = $this->DeflMargin - $this->DefrMargin;
                    } // *COLUMNS*
                    else {
                        $xadj = 0;
                    } // *COLUMNS*
                    $x = ($this->DeflMargin - $xadj + ($this->w - ($this->DeflMargin + $this->DefrMargin)) / 2) - ($w / 2); // *COLUMNS*
                } // *COLUMNS*
                else {  // *COLUMNS*
                    $x = ($this->lMargin + ($this->pgwidth) / 2) - ($w / 2);
                } // *COLUMNS*
                $y = ($this->tMargin + ($this->h - ($this->tMargin + $this->bMargin)) / 2) - ($h / 2);
            } else { // default P - centred on whole page
                $x = ($this->w / 2) - ($w / 2);
                $y = ($this->h / 2) - ($h / 2);
            }
            /* -- IMAGES-WMF -- */
            if ($info['type'] == 'wmf') {
                $sx = $w * Mpdf::SCALE / $info['w'];
                $sy = -$h * Mpdf::SCALE / $info['h'];
                $outstring = sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, $sy, $x * Mpdf::SCALE - $sx * $info['x'], (($this->h - $y) * Mpdf::SCALE) - $sy * $info['y'], $info['i']);
            } else {            /* -- END IMAGES-WMF -- */
                if ($info['type'] == 'svg') {
                    $sx = $w * Mpdf::SCALE / $info['w'];
                    $sy = -$h * Mpdf::SCALE / $info['h'];
                    $outstring = sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, $sy, $x * Mpdf::SCALE - $sx * $info['x'], (($this->h - $y) * Mpdf::SCALE) - $sy * $info['y'], $info['i']);
                } else {
                    $outstring = sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q", $w * Mpdf::SCALE, $h * Mpdf::SCALE, $x * Mpdf::SCALE, ($this->h - ($y + $h)) * Mpdf::SCALE, $info['i']);
                }
            }

            if ($this->watermarkImgBehind) {
                $outstring = $this->watermarkImgAlpha . "\n" . $outstring . "\n" . $this->SetAlpha(1, 'Normal', true) . "\n";
                $this->pages[$this->page] = preg_replace('/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/', "\n" . $outstring . "\n" . '\\1', $this->pages[$this->page]);
            } else {
                $this->_out($outstring);
            }

            return 0;
        } // end of IF watermark
        /* -- END WATERMARK -- */

        if ($constrain) {
            // Automatically resize to maximum dimensions of page if too large
            if (isset($this->blk[$this->blklvl]['inner_width']) && $this->blk[$this->blklvl]['inner_width']) {
                $maxw = $this->blk[$this->blklvl]['inner_width'];
            } else {
                $maxw = $this->pgwidth;
            }
            if ($w > $maxw) {
                $w = $maxw;
                $h = abs($w * $info['h'] / $info['w']);
            }
            if ($h > $this->h - ($this->tMargin + $this->bMargin + 1)) {  // see below - +10 to avoid drawing too close to border of page
                $h = $this->h - ($this->tMargin + $this->bMargin + 1);
                if ($this->fullImageHeight) {
                    $h = $this->fullImageHeight;
                }
                $w = abs($h * $info['w'] / $info['h']);
            }


            // Avoid drawing out of the paper(exceeding width limits).
            // if ( ($x + $w) > $this->fw ) {
            if (($x + $w) > $this->w) {
                $x = $this->lMargin;
                $y += 5;
            }

            $changedpage = false;
            $oldcolumn = $this->CurrCol;
            // Avoid drawing out of the page.
            if ($y + $h > $this->PageBreakTrigger and ! $this->InFooter and $this->AcceptPageBreak()) {
                $this->AddPage($this->CurOrientation);
                // Added to correct for OddEven Margins
                $x = $x + $this->MarginCorrection;
                $y = $this->tMargin; // mPDF 5.7.3
                $changedpage = true;
            }
            /* -- COLUMNS -- */
            // COLS
            // COLUMN CHANGE
            if ($this->CurrCol != $oldcolumn) {
                $y = $this->y0;
                $x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
                $this->x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
            }
            /* -- END COLUMNS -- */
        } // end of IF constrain

        /* -- IMAGES-WMF -- */
        if ($info['type'] == 'wmf') {
            $sx = $w * Mpdf::SCALE / $info['w'];
            $sy = -$h * Mpdf::SCALE / $info['h'];
            $outstring = sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, $sy, $x * Mpdf::SCALE - $sx * $info['x'], (($this->h - $y) * Mpdf::SCALE) - $sy * $info['y'], $info['i']);
        } else {        /* -- END IMAGES-WMF -- */
            if ($info['type'] == 'svg') {
                $sx = $w * Mpdf::SCALE / $info['w'];
                $sy = -$h * Mpdf::SCALE / $info['h'];
                $outstring = sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q', $sx, $sy, $x * Mpdf::SCALE - $sx * $info['x'], (($this->h - $y) * Mpdf::SCALE) - $sy * $info['y'], $info['i']);
            } else {
                $outstring = sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q", $w * Mpdf::SCALE, $h * Mpdf::SCALE, $x * Mpdf::SCALE, ($this->h - ($y + $h)) * Mpdf::SCALE, $info['i']);
            }
        }

        if ($paint) {
            $this->_out($outstring);
            if ($link) {
                $this->Link($x, $y, $w, $h, $link);
            }

            // Avoid writing text on top of the image. // THIS WAS OUTSIDE THE if ($paint) bit!!!!!!!!!!!!!!!!
            $this->y = $y + $h;
        }

        // Return width-height array
        $sizesarray['WIDTH'] = $w;
        $sizesarray['HEIGHT'] = $h;
        $sizesarray['X'] = $x; // Position before painting image
        $sizesarray['Y'] = $y; // Position before painting image
        $sizesarray['OUTPUT'] = $outstring;

        $sizesarray['IMAGE_ID'] = $info['i'];
        $sizesarray['itype'] = $info['type'];
        $sizesarray['set-dpi'] = (isset($info['set-dpi']) ? $info['set-dpi'] : 0);
        return $sizesarray;
    }

    // =============================================================
    // =============================================================
    // =============================================================
    // =============================================================
    // =============================================================
    /* -- HTML-CSS -- */

    public function inlineObject($type, $x, $y, $objattr, $Lmargin, $widthUsed, $maxWidth, $lineHeight, $paint = false, $is_table = false)
    {
        if ($is_table) {
            $k = $this->shrin_k;
        } else {
            $k = 1;
        }

        // NB $x is only used when paint=true
        // Lmargin not used
        $w = 0;
        if (isset($objattr['width'])) {
            $w = $objattr['width'] / $k;
        }
        $h = 0;
        if (isset($objattr['height'])) {
            $h = abs($objattr['height'] / $k);
        }
        $widthLeft = $maxWidth - $widthUsed;
        $maxHeight = $this->h - ($this->tMargin + $this->bMargin + 10);
        if ($this->fullImageHeight) {
            $maxHeight = $this->fullImageHeight;
        }
        // For Images
        if (isset($objattr['border_left'])) {
            $extraWidth = ($objattr['border_left']['w'] + $objattr['border_right']['w'] + $objattr['margin_left'] + $objattr['margin_right']) / $k;
            $extraHeight = ($objattr['border_top']['w'] + $objattr['border_bottom']['w'] + $objattr['margin_top'] + $objattr['margin_bottom']) / $k;

            if ($type == 'image' || $type == 'barcode' || $type == 'textcircle') {
                $extraWidth += ($objattr['padding_left'] + $objattr['padding_right']) / $k;
                $extraHeight += ($objattr['padding_top'] + $objattr['padding_bottom']) / $k;
            }
        }

        if (!isset($objattr['vertical-align'])) {
            if ($objattr['type'] == 'select') {
                $objattr['vertical-align'] = 'M';
            } else {
                $objattr['vertical-align'] = 'BS';
            }
        } // mPDF 6

        if ($type == 'image' || (isset($objattr['subtype']) && $objattr['subtype'] == 'IMAGE')) {
            if (isset($objattr['itype']) && ($objattr['itype'] == 'wmf' || $objattr['itype'] == 'svg')) {
                $file = $objattr['file'];
                $info = $this->formobjects[$file];
            } elseif (isset($objattr['file'])) {
                $file = $objattr['file'];
                $info = $this->images[$file];
            }
        }
        if ($type == 'annot' || $type == 'bookmark' || $type == 'indexentry' || $type == 'toc') {
            $w = 0.00001;
            $h = 0.00001;
        }

        // TEST whether need to skipline
        if (!$paint) {
            if ($type == 'hr') { // always force new line
                if (($y + $h + $lineHeight > $this->PageBreakTrigger) && !$this->InFooter && !$is_table) {
                    return [-2, $w, $h];
                } // New page + new line
                else {
                    return [1, $w, $h];
                } // new line
            } else {
                // LIST MARKERS // mPDF 6  Lists
                $displayheight = $h;
                $displaywidth = $w;
                if ($objattr['type'] == 'image' && isset($objattr['listmarker']) && $objattr['listmarker']) {
                    $displayheight = 0;
                    if ($objattr['listmarkerposition'] == 'outside') {
                        $displaywidth = 0;
                    }
                }

                if ($widthUsed > 0 && $displaywidth > $widthLeft && (!$is_table || $type != 'image')) {  // New line needed
                    // mPDF 6  Lists
                    if (($y + $displayheight + $lineHeight > $this->PageBreakTrigger) && !$this->InFooter) {
                        return [-2, $w, $h];
                    } // New page + new line
                    return [1, $w, $h]; // new line
                } elseif ($widthUsed > 0 && $displaywidth > $widthLeft && $is_table) {  // New line needed in TABLE
                    return [1, $w, $h]; // new line
                } // Will fit on line but NEW PAGE REQUIRED
                elseif (($y + $displayheight > $this->PageBreakTrigger) && !$this->InFooter && !$is_table) {
                    return [-1, $w, $h];
                } // mPDF 6  Lists
                else {
                    return [0, $w, $h];
                }
            }
        }

        if ($type == 'annot' || $type == 'bookmark' || $type == 'indexentry' || $type == 'toc') {
            $w = 0.00001;
            $h = 0.00001;
            $objattr['BORDER-WIDTH'] = 0;
            $objattr['BORDER-HEIGHT'] = 0;
            $objattr['BORDER-X'] = $x;
            $objattr['BORDER-Y'] = $y;
            $objattr['INNER-WIDTH'] = 0;
            $objattr['INNER-HEIGHT'] = 0;
            $objattr['INNER-X'] = $x;
            $objattr['INNER-Y'] = $y;
        }

        if ($type == 'image') {
            // Automatically resize to width remaining
            if ($w > ($widthLeft + 0.0001) && !$is_table) { // mPDF 5.7.4  0.0001 to allow for rounding errors when w==maxWidth
                $w = $widthLeft;
                $h = abs($w * $info['h'] / $info['w']);
            }
            $img_w = $w - $extraWidth;
            $img_h = $h - $extraHeight;

            $objattr['BORDER-WIDTH'] = $img_w + $objattr['padding_left'] / $k + $objattr['padding_right'] / $k + (($objattr['border_left']['w'] / $k + $objattr['border_right']['w'] / $k) / 2);
            $objattr['BORDER-HEIGHT'] = $img_h + $objattr['padding_top'] / $k + $objattr['padding_bottom'] / $k + (($objattr['border_top']['w'] / $k + $objattr['border_bottom']['w'] / $k) / 2);
            $objattr['BORDER-X'] = $x + $objattr['margin_left'] / $k + (($objattr['border_left']['w'] / $k) / 2);
            $objattr['BORDER-Y'] = $y + $objattr['margin_top'] / $k + (($objattr['border_top']['w'] / $k) / 2);
            $objattr['INNER-WIDTH'] = $img_w;
            $objattr['INNER-HEIGHT'] = $img_h;
            $objattr['INNER-X'] = $x + $objattr['padding_left'] / $k + $objattr['margin_left'] / $k + ($objattr['border_left']['w'] / $k);
            $objattr['INNER-Y'] = $y + $objattr['padding_top'] / $k + $objattr['margin_top'] / $k + ($objattr['border_top']['w'] / $k);
            $objattr['ID'] = $info['i'];
        }

        if ($type == 'input' && $objattr['subtype'] == 'IMAGE') {
            $img_w = $w - $extraWidth;
            $img_h = $h - $extraHeight;
            $objattr['BORDER-WIDTH'] = $img_w + (($objattr['border_left']['w'] / $k + $objattr['border_right']['w'] / $k) / 2);
            $objattr['BORDER-HEIGHT'] = $img_h + (($objattr['border_top']['w'] / $k + $objattr['border_bottom']['w'] / $k) / 2);
            $objattr['BORDER-X'] = $x + $objattr['margin_left'] / $k + (($objattr['border_left']['w'] / $k) / 2);
            $objattr['BORDER-Y'] = $y + $objattr['margin_top'] / $k + (($objattr['border_top']['w'] / $k) / 2);
            $objattr['INNER-WIDTH'] = $img_w;
            $objattr['INNER-HEIGHT'] = $img_h;
            $objattr['INNER-X'] = $x + $objattr['margin_left'] / $k + ($objattr['border_left']['w'] / $k);
            $objattr['INNER-Y'] = $y + $objattr['margin_top'] / $k + ($objattr['border_top']['w'] / $k);
            $objattr['ID'] = $info['i'];
        }

        if ($type == 'barcode' || $type == 'textcircle') {
            $b_w = $w - $extraWidth;
            $b_h = $h - $extraHeight;
            $objattr['BORDER-WIDTH'] = $b_w + $objattr['padding_left'] / $k + $objattr['padding_right'] / $k + (($objattr['border_left']['w'] / $k + $objattr['border_right']['w'] / $k) / 2);
            $objattr['BORDER-HEIGHT'] = $b_h + $objattr['padding_top'] / $k + $objattr['padding_bottom'] / $k + (($objattr['border_top']['w'] / $k + $objattr['border_bottom']['w'] / $k) / 2);
            $objattr['BORDER-X'] = $x + $objattr['margin_left'] / $k + (($objattr['border_left']['w'] / $k) / 2);
            $objattr['BORDER-Y'] = $y + $objattr['margin_top'] / $k + (($objattr['border_top']['w'] / $k) / 2);
            $objattr['INNER-X'] = $x + $objattr['padding_left'] / $k + $objattr['margin_left'] / $k + ($objattr['border_left']['w'] / $k);
            $objattr['INNER-Y'] = $y + $objattr['padding_top'] / $k + $objattr['margin_top'] / $k + ($objattr['border_top']['w'] / $k);
            $objattr['INNER-WIDTH'] = $b_w;
            $objattr['INNER-HEIGHT'] = $b_h;
        }


        if ($type == 'textarea') {
            // Automatically resize to width remaining
            if ($w > $widthLeft && !$is_table) {
                $w = $widthLeft;
            }
            // This used to resize height to maximum remaining on page ? why. Causes problems when in table and causing a new column
            // if (($y + $h > $this->PageBreakTrigger) && !$this->InFooter) {
            //  $h=$this->h - $y - $this->bMargin;
            // }
        }

        if ($type == 'hr') {
            if ($is_table) {
                $objattr['INNER-WIDTH'] = $maxWidth * $objattr['W-PERCENT'] / 100;
                $objattr['width'] = $objattr['INNER-WIDTH'];
                $w = $maxWidth;
            } else {
                if ($w > $maxWidth) {
                    $w = $maxWidth;
                }
                $objattr['INNER-WIDTH'] = $w;
                $w = $maxWidth;
            }
        }



        if (($type == 'select') || ($type == 'input' && ($objattr['subtype'] == 'TEXT' || $objattr['subtype'] == 'PASSWORD'))) {
            // Automatically resize to width remaining
            if ($w > $widthLeft && !$is_table) {
                $w = $widthLeft;
            }
        }

        if ($type == 'textarea' || $type == 'select' || $type == 'input') {
            if (isset($objattr['fontsize'])) {
                $objattr['fontsize'] /= $k;
            }
            if (isset($objattr['linewidth'])) {
                $objattr['linewidth'] /= $k;
            }
        }

        if (!isset($objattr['BORDER-Y'])) {
            $objattr['BORDER-Y'] = 0;
        }
        if (!isset($objattr['BORDER-X'])) {
            $objattr['BORDER-X'] = 0;
        }
        if (!isset($objattr['INNER-Y'])) {
            $objattr['INNER-Y'] = 0;
        }
        if (!isset($objattr['INNER-X'])) {
            $objattr['INNER-X'] = 0;
        }

        // Return width-height array
        $objattr['OUTER-WIDTH'] = $w;
        $objattr['OUTER-HEIGHT'] = $h;
        $objattr['OUTER-X'] = $x;
        $objattr['OUTER-Y'] = $y;
        return $objattr;
    }

    /* -- END HTML-CSS -- */

    // =============================================================
    // =============================================================
    // =============================================================
    // =============================================================
    // =============================================================

    public function SetLineJoin($mode = 0)
    {
        $s = sprintf('%d j', $mode);
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['LineJoin']) && $this->pageoutput[$this->page]['LineJoin'] != $s) || !isset($this->pageoutput[$this->page]['LineJoin']))) {
            $this->_out($s);
        }
        $this->pageoutput[$this->page]['LineJoin'] = $s;
    }

    public function SetLineCap($mode = 2)
    {
        $s = sprintf('%d J', $mode);
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['LineCap']) && $this->pageoutput[$this->page]['LineCap'] != $s) || !isset($this->pageoutput[$this->page]['LineCap']))) {
            $this->_out($s);
        }
        $this->pageoutput[$this->page]['LineCap'] = $s;
    }

    public function SetDash($black = false, $white = false)
    {
        if ($black and $white) {
            $s = sprintf('[%.3F %.3F] 0 d', $black * Mpdf::SCALE, $white * Mpdf::SCALE);
        } else {
            $s = '[] 0 d';
        }
        if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['Dash']) && $this->pageoutput[$this->page]['Dash'] != $s) || !isset($this->pageoutput[$this->page]['Dash']))) {
            $this->_out($s);
        }
        $this->pageoutput[$this->page]['Dash'] = $s;
    }

    public function SetDisplayPreferences($preferences)
    {
        // String containing any or none of /HideMenubar/HideToolbar/HideWindowUI/DisplayDocTitle/CenterWindow/FitWindow
        $this->DisplayPreferences .= $preferences;
    }

    public function Ln($h = '', $collapsible = 0)
    {
        // Added collapsible to allow collapsible top-margin on new page
        // Line feed; default value is last cell height
        $this->x = $this->lMargin + $this->blk[$this->blklvl]['outer_left_margin'];
        if ($collapsible && ($this->y == $this->tMargin) && (!$this->ColActive)) {
            $h = 0;
        }
        if (is_string($h)) {
            $this->y+=$this->lasth;
        } else {
            $this->y+=$h;
        }
    }

    /* -- HTML-CSS -- */

    public function DivLn($h, $level = -3, $move_y = true, $collapsible = false, $state = 0)
    {
        // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
        // Used in Columns and keep-with-table i.e. "kwt"
        // writes background block by block so it can be repositioned
        // and also used in writingFlowingBlock at top and bottom of blocks to move y (not to draw/paint anything)
        // adds lines (y) where DIV bgcolors are filled in
        // this->x is returned as it was
        // allows .00001 as nominal height used for bookmarks/annotations etc.
        if ($collapsible && (sprintf("%0.4f", $this->y) == sprintf("%0.4f", $this->tMargin)) && (!$this->ColActive)) {
            return;
        }

        // mPDF 6 Columns
        //   if ($collapsible && (sprintf("%0.4f", $this->y)==sprintf("%0.4f", $this->y0)) && ($this->ColActive) && $this->CurrCol == 0) { return; }    // *COLUMNS*
        if ($collapsible && (sprintf("%0.4f", $this->y) == sprintf("%0.4f", $this->y0)) && ($this->ColActive)) {
            return;
        } // *COLUMNS*
        // Still use this method if columns or keep-with-table, as it allows repositioning later
        // otherwise, now uses PaintDivBB()
        if (!$this->ColActive && !$this->kwt) {
            if ($move_y && !$this->ColActive) {
                $this->y += $h;
            }
            return;
        }

        if ($level == -3) {
            $level = $this->blklvl;
        }
        $firstblockfill = $this->GetFirstBlockFill();
        if ($firstblockfill && $this->blklvl > 0 && $this->blklvl >= $firstblockfill) {
            $last_x = 0;
            $last_w = 0;
            $last_fc = $this->FillColor;
            $bak_x = $this->x;
            $bak_h = $this->divheight;
            $this->divheight = 0; // Temporarily turn off divheight - as Cell() uses it to check for PageBreak
            for ($blvl = $firstblockfill; $blvl <= $level; $blvl++) {
                $this->x = $this->lMargin + $this->blk[$blvl]['outer_left_margin'];
                // mPDF 6
                if ($this->blk[$blvl]['bgcolor']) {
                    $this->SetFColor($this->blk[$blvl]['bgcolorarray']);
                }
                if ($last_x != ($this->lMargin + $this->blk[$blvl]['outer_left_margin']) || ($last_w != $this->blk[$blvl]['width']) || $last_fc != $this->FillColor || (isset($this->blk[$blvl]['border_top']['s']) && $this->blk[$blvl]['border_top']['s']) || (isset($this->blk[$blvl]['border_bottom']['s']) && $this->blk[$blvl]['border_bottom']['s']) || (isset($this->blk[$blvl]['border_left']['s']) && $this->blk[$blvl]['border_left']['s']) || (isset($this->blk[$blvl]['border_right']['s']) && $this->blk[$blvl]['border_right']['s'])) {
                    $x = $this->x;
                    $this->Cell(($this->blk[$blvl]['width']), $h, '', '', 0, '', 1);
                    $this->x = $x;
                    if (!$this->keep_block_together && !$this->writingHTMLheader && !$this->writingHTMLfooter) {
                        // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
                        if ($blvl == $this->blklvl) {
                            $this->PaintDivLnBorder($state, $blvl, $h);
                        } else {
                            $this->PaintDivLnBorder(0, $blvl, $h);
                        }
                    }
                }
                $last_x = $this->lMargin + $this->blk[$blvl]['outer_left_margin'];
                $last_w = $this->blk[$blvl]['width'];
                $last_fc = $this->FillColor;
            }
            // Reset current block fill
            if (isset($this->blk[$this->blklvl]['bgcolorarray'])) {
                $bcor = $this->blk[$this->blklvl]['bgcolorarray'];
                $this->SetFColor($bcor);
            }
            $this->x = $bak_x;
            $this->divheight = $bak_h;
        }
        if ($move_y) {
            $this->y += $h;
        }
    }

    /* -- END HTML-CSS -- */

    public function SetX($x)
    {
        // Set x position
        if ($x >= 0) {
            $this->x = $x;
        } else {
            $this->x = $this->w + $x;
        }
    }

    public function SetY($y)
    {
        // Set y position and reset x
        $this->x = $this->lMargin;
        if ($y >= 0) {
            $this->y = $y;
        } else {
            $this->y = $this->h + $y;
        }
    }

    public function SetXY($x, $y)
    {
        // Set x and y positions
        $this->SetY($y);
        $this->SetX($x);
    }

    public function Output($name = '', $dest = '')
    {
        $this->logger->debug(sprintf('PDF generated in %.6F seconds', microtime(true) - $this->time0), ['context' => LogContext::STATISTICS]);

        // Finish document if necessary
        if ($this->state < 3) {
            $this->Close();
        }

        if ($this->debug && error_get_last()) {
            $e = error_get_last();
            if (($e['type'] < 2048 && $e['type'] != 8) || (intval($e['type']) & intval(ini_get("error_reporting")))) {
                throw new \Mpdf\MpdfException(
                    sprintf('Error detected. PDF file generation aborted: %s', $e['message']),
                    $e['type'],
                    1,
                    $e['file'],
                    $e['line']
                );
            }
        }

        if (($this->PDFA || $this->PDFX) && $this->encrypted) {
            throw new \Mpdf\MpdfException('PDF/A1-b or PDF/X1-a does not permit encryption of documents.');
        }

        if (count($this->PDFAXwarnings) && (($this->PDFA && !$this->PDFAauto) || ($this->PDFX && !$this->PDFXauto))) {
            if ($this->PDFA) {
                $standard = 'PDFA/1-b';
                $option = '$mpdf->PDFAauto';
            } else {
                $standard = 'PDFX/1-a ';
                $option = '$mpdf->PDFXauto';
            }

            $this->logger->warning(sprintf('PDF could not be generated as it stands as a %s compliant file.', $standard), ['context' => LogContext::PDFA_PDFX]);
            $this->logger->warning(sprintf('These issues can be automatically fixed by mPDF using %s = true;', $option), ['context' => LogContext::PDFA_PDFX]);
            $this->logger->warning(sprintf('Action that mPDF will take to automatically force %s compliance are shown further in the log.', $standard), ['context' => LogContext::PDFA_PDFX]);

            $this->PDFAXwarnings = array_unique($this->PDFAXwarnings);
            foreach ($this->PDFAXwarnings as $w) {
                $this->logger->warning($w, ['context' => LogContext::PDFA_PDFX]);
            }

            throw new \Mpdf\MpdfException('PDFA/PDFX warnings generated. See log for further details');
        }

        $this->logger->debug(sprintf('Compiled in %.6F seconds', microtime(true) - $this->time0), ['context' => LogContext::STATISTICS]);
        $this->logger->debug(sprintf('Peak Memory usage %s MB', number_format(memory_get_peak_usage(true) / (1024 * 1024), 2)), ['context' => LogContext::STATISTICS]);
        $this->logger->debug(sprintf('PDF file size %s kB', number_format(strlen($this->buffer) / 1024)), ['context' => LogContext::STATISTICS]);
        $this->logger->debug(sprintf('%d fonts used', count($this->fonts)), ['context' => LogContext::STATISTICS]);

        if (is_bool($dest)) {
            $dest = $dest ? Destination::DOWNLOAD : Destination::FILE;
        }

        $dest = strtoupper($dest);
        if (empty($dest)) {
            if (empty($name)) {
                $name = 'mpdf.pdf';
                $dest = Destination::INLINE;
            } else {
                $dest = Destination::FILE;
            }
        }

        switch ($dest) {

            case Destination::INLINE:

                if (headers_sent($filename, $line)) {
                    throw new \Mpdf\MpdfException(
                        sprintf('Data has already been sent to output (%s at line %s), unable to output PDF file', $filename, $line)
                    );
                }

                if ($this->debug && !$this->allow_output_buffering && ob_get_contents()) {
                    throw new \Mpdf\MpdfException('Output has already been sent from the script - PDF file generation aborted.');
                }

                // We send to a browser
                if (PHP_SAPI !== 'cli') {
                    header('Content-Type: application/pdf');

                    if (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) || empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                        // don't use length if server using compression
                        header('Content-Length: ' . strlen($this->buffer));
                    }

                    header('Content-disposition: inline; filename="' . $name . '"');
                    header('Cache-Control: public, must-revalidate, max-age=0');
                    header('Pragma: public');
                    header('X-Generator: mPDF ' . static::VERSION);
                    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                }

                echo $this->buffer;

                break;

            case Destination::DOWNLOAD:

                if (headers_sent()) {
                    throw new \Mpdf\MpdfException('Data has already been sent to output, unable to output PDF file');
                }

                header('Content-Description: File Transfer');
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: public, must-revalidate, max-age=0');
                header('Pragma: public');
                header('X-Generator: mPDF ' . static::VERSION);
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Content-Type: application/pdf');

                if (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) || empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                    // don't use length if server using compression
                    header('Content-Length: ' . strlen($this->buffer));
                }

                header('Content-Disposition: attachment; filename="' . $name . '"');

                echo $this->buffer;

                break;

            case Destination::FILE:
                $f = fopen($name, 'wb');

                if (!$f) {
                    throw new \Mpdf\MpdfException(sprintf('Unable to create output file %s', $name));
                }

                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);

                break;

            case Destination::STRING_RETURN:
                $this->cache->clearOld();
                return $this->buffer;

            default:
                throw new \Mpdf\MpdfException(sprintf('Incorrect output destination %s', $dest));
        }

        $this->cache->clearOld();
    }

    /* -- ANNOTATIONS -- */

    public function Annotation($text, $x = 0, $y = 0, $icon = 'Note', $author = '', $subject = '', $opacity = 0, $colarray = false, $popup = '', $file = '')
    {
        if (is_array($colarray) && count($colarray) == 3) {
            $colarray = $this->colorConverter->convert('rgb(' . $colarray[0] . ',' . $colarray[1] . ',' . $colarray[2] . ')', $this->PDFAXwarnings);
        }
        if ($colarray === false) {
            $colarray = $this->colorConverter->convert('yellow', $this->PDFAXwarnings);
        }
        if ($x == 0) {
            $x = $this->x;
        }
        if ($y == 0) {
            $y = $this->y;
        }
        $page = $this->page;
        if ($page < 1) { // Document has not been started - assume it's for first page
            $page = 1;
            if ($x == 0) {
                $x = $this->lMargin;
            }
            if ($y == 0) {
                $y = $this->tMargin;
            }
        }

        if ($this->PDFA || $this->PDFX) {
            if (($this->PDFA && !$this->PDFAauto) || ($this->PDFX && !$this->PDFXauto)) {
                $this->PDFAXwarnings[] = "Annotation markers cannot be semi-transparent in PDFA1-b or PDFX/1-a, so they may make underlying text unreadable. (Annotation markers moved to right margin)";
            }
            $x = ($this->w) - $this->rMargin * 0.66;
        }
        if (!$this->annotMargin) {
            $y -= $this->FontSize / 2;
        }

        if (!$opacity && $this->annotMargin) {
            $opacity = 1;
        } elseif (!$opacity) {
            $opacity = $this->annotOpacity;
        }

        $an = ['txt' => $text, 'x' => $x, 'y' => $y, 'opt' => ['Icon' => $icon, 'T' => $author, 'Subj' => $subject, 'C' => $colarray, 'CA' => $opacity, 'popup' => $popup, 'file' => $file]];

        if ($this->keep_block_together) { // don't write yet
            return;
        } elseif ($this->table_rotate) {
            $this->tbrot_Annots[$this->page][] = $an;
            return;
        } elseif ($this->kwt) {
            $this->kwt_Annots[$this->page][] = $an;
            return;
        }
        if ($this->writingHTMLheader || $this->writingHTMLfooter) {
            $this->HTMLheaderPageAnnots[] = $an;
            return;
        }
        // Put an Annotation on the page
        $this->PageAnnots[$page][] = $an;
        /* -- COLUMNS -- */
        // Save cross-reference to Column buffer
        $ref = count($this->PageAnnots[$this->page]) - 1;
        $this->columnAnnots[$this->CurrCol][intval($this->x)][intval($this->y)] = $ref;
        /* -- END COLUMNS -- */
    }

    /* -- END ANNOTATIONS -- */

    public function getFileContentsByCurl($url, &$data)
    {
        $this->logger->debug(sprintf('Fetching (cURL) content of remote URL "%s"', $url), ['context' => LogContext::REMOTE_CONTENT]);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:13.0) Gecko/20100101 Firefox/13.0.1'); // mPDF 5.7.4
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->curlTimeout);

        if ($this->curlFollowLocation) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }

        if ($this->curlAllowUnsafeSslRequests) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $data = curl_exec($ch);
        curl_close($ch);
    }

    public function getFileContentsBySocket($url, &$data)
    {
        $this->logger->debug(sprintf('Fetching (socket) content of remote URL "%s"', $url), ['context' => LogContext::REMOTE_CONTENT]);
        // mPDF 5.7.3

        $timeout = 1;
        $p = parse_url($url);
        $file = $p['path'];
        if ($p['scheme'] == 'https') {
            $prefix = 'ssl://';
            $port = ($p['port'] ? $p['port'] : 443);
        } else {
            $prefix = '';
            $port = ($p['port'] ? $p['port'] : 80);
        }
        if ($p['query']) {
            $file .= '?' . $p['query'];
        }
        if (!($fh = @fsockopen($prefix . $p['host'], $port, $errno, $errstr, $timeout))) {
            return false;
        }

        $getstring = "GET " . $file . " HTTP/1.0 \r\n" .
            "Host: " . $p['host'] . " \r\n" .
            "Connection: close\r\n\r\n";

        fwrite($fh, $getstring);

        // Get rid of HTTP header
        $s = fgets($fh, 1024);
        if (!$s) {
            return false;
        }
        while (!feof($fh)) {
            $s = fgets($fh, 1024);
            if ($s == "\r\n") {
                break;
            }
        }
        $data = '';

        while (!feof($fh)) {
            $data .= fgets($fh, 1024);
        }

        fclose($fh);
    }

    /* -- WATERMARK -- */

    // add a watermark
    public function watermark($texte, $angle = 45, $fontsize = 96, $alpha = 0.2)
    {
        if ($this->PDFA || $this->PDFX) {
            throw new \Mpdf\MpdfException('PDFA and PDFX do not permit transparency, so mPDF does not allow Watermarks!');
        }

        if (!$this->watermark_font) {
            $this->watermark_font = $this->default_font;
        }

        $this->SetFont($this->watermark_font, "B", $fontsize, false); // Don't output
        $texte = $this->purify_utf8_text($texte);

        if ($this->text_input_as_HTML) {
            $texte = $this->all_entities_to_utf8($texte);
        }

        if ($this->usingCoreFont) {
            $texte = mb_convert_encoding($texte, $this->mb_enc, 'UTF-8');
        }

        // DIRECTIONALITY
        if (preg_match("/([" . $this->pregRTLchars . "])/u", $texte)) {
            $this->biDirectional = true;
        } // *OTL*

        $textvar = 0;
        $save_OTLtags = $this->OTLtags;
        $this->OTLtags = [];
        if ($this->useKerning) {
            if ($this->CurrentFont['haskernGPOS']) {
                $this->OTLtags['Plus'] .= ' kern';
            } else {
                $textvar = ($textvar | TextVars::FC_KERNING);
            }
        }

        /* -- OTL -- */
        // Use OTL OpenType Table Layout - GSUB & GPOS
        if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
            $texte = $this->otl->applyOTL($texte, $this->CurrentFont['useOTL']);
            $OTLdata = $this->otl->OTLdata;
        }
        /* -- END OTL -- */
        $this->OTLtags = $save_OTLtags;

        $this->magic_reverse_dir($texte, $this->directionality, $OTLdata);

        $this->SetAlpha($alpha);

        $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));

        $szfont = $fontsize;
        $loop = 0;
        $maxlen = (min($this->w, $this->h) ); // sets max length of text as 7/8 width/height of page

        while ($loop == 0) {
            $this->SetFont($this->watermark_font, "B", $szfont, false); // Don't output
            $offset = ((sin(deg2rad($angle))) * ($szfont / Mpdf::SCALE));

            $strlen = $this->GetStringWidth($texte, true, $OTLdata, $textvar);
            if ($strlen > $maxlen - $offset) {
                $szfont --;
            } else {
                $loop ++;
            }
        }

        $this->SetFont($this->watermark_font, "B", $szfont - 0.1, true, true); // Output The -0.1 is because SetFont above is not written to PDF

        // Repeating it will not output anything as mPDF thinks it is set
        $adj = ((cos(deg2rad($angle))) * ($strlen / 2));
        $opp = ((sin(deg2rad($angle))) * ($strlen / 2));

        $wx = ($this->w / 2) - $adj + $offset / 3;
        $wy = ($this->h / 2) + $opp;

        $this->Rotate($angle, $wx, $wy);
        $this->Text($wx, $wy, $texte, $OTLdata, $textvar);
        $this->Rotate(0);
        $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));

        $this->SetAlpha(1);
    }

    public function watermarkImg($src, $alpha = 0.2)
    {
        if ($this->PDFA || $this->PDFX) {
            throw new \Mpdf\MpdfException('PDFA and PDFX do not permit transparency, so mPDF does not allow Watermarks!');
        }

        if ($this->watermarkImgBehind) {
            $this->watermarkImgAlpha = $this->SetAlpha($alpha, 'Normal', true);
        } else {
            $this->SetAlpha($alpha, $this->watermarkImgAlphaBlend);
        }

        $this->Image($src, 0, 0, 0, 0, '', '', true, true, true);

        if (!$this->watermarkImgBehind) {
            $this->SetAlpha(1);
        }
    }

    /* -- END WATERMARK -- */

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle*=M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * Mpdf::SCALE;
            $cy = ($this->h - $y) * Mpdf::SCALE;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.3F %.3F cm 1 0 0 1 %.3F %.3F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function CircularText($x, $y, $r, $text, $align = 'top', $fontfamily = '', $fontsize = 0, $fontstyle = '', $kerning = 120, $fontwidth = 100, $divider = '')
    {
        if (empty($this->directWrite)) {
            $this->directWrite = new DirectWrite($this, $this->otl, $this->sizeConverter, $this->colorConverter);
        }

        $this->directWrite->CircularText($x, $y, $r, $text, $align, $fontfamily, $fontsize, $fontstyle, $kerning, $fontwidth, $divider);
    }

    // From Invoice
    public function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $hp = $this->h;

        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' or $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }

        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.3F %.3F m', ($x + $r) * Mpdf::SCALE, ($hp - $y) * Mpdf::SCALE));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.3F %.3F l', $xc * Mpdf::SCALE, ($hp - $y) * Mpdf::SCALE));

        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.3F %.3F l', ($x + $w) * Mpdf::SCALE, ($hp - $yc) * Mpdf::SCALE));

        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.3F %.3F l', $xc * Mpdf::SCALE, ($hp - ($y + $h)) * Mpdf::SCALE));

        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.3F %.3F l', ($x) * Mpdf::SCALE, ($hp - $yc) * Mpdf::SCALE));

        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    // ====================================================



    /* -- DIRECTW -- */
    public function Shaded_box($text, $font = '', $fontstyle = 'B', $szfont = '', $width = '70%', $style = 'DF', $radius = 2.5, $fill = '#FFFFFF', $color = '#000000', $pad = 2)
    {
        // F (shading - no line),S (line, no shading),DF (both)
        if (empty($this->directWrite)) {
            $this->directWrite = new DirectWrite($this, $this->otl, $this->sizeConverter, $this->colorConverter);
        }
        $this->directWrite->Shaded_box($text, $font, $fontstyle, $szfont, $width, $style, $radius, $fill, $color, $pad);
    }

    /* -- END DIRECTW -- */

    public function UTF8StringToArray($str, $addSubset = true)
    {
        $out = [];
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $uni = -1;
            $h = ord($str[$i]);
            if ($h <= 0x7F) {
                $uni = $h;
            } elseif ($h >= 0xC2) {
                if (($h <= 0xDF) && ($i < $len - 1)) {
                    $uni = ($h & 0x1F) << 6 | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xEF) && ($i < $len - 2)) {
                    $uni = ($h & 0x0F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xF4) && ($i < $len - 3)) {
                    $uni = ($h & 0x0F) << 18 | (ord($str[++$i]) & 0x3F) << 12 | (ord($str[++$i]) & 0x3F) << 6 | (ord($str[++$i]) & 0x3F);
                }
            }
            if ($uni >= 0) {
                $out[] = $uni;
                if ($addSubset && isset($this->CurrentFont['subset'])) {
                    $this->CurrentFont['subset'][$uni] = $uni;
                }
            }
        }
        return $out;
    }

    // Convert utf-8 string to <HHHHHH> for Font Subsets
    public function UTF8toSubset($str)
    {
        $ret = '<';
        // $str = preg_replace('/'.preg_quote($this->aliasNbPg,'/').'/', chr(7), $str );    // mPDF 6 deleted
        // $str = preg_replace('/'.preg_quote($this->aliasNbPgGp,'/').'/', chr(8), $str );  // mPDF 6 deleted
        $unicode = $this->UTF8StringToArray($str);
        $orig_fid = $this->CurrentFont['subsetfontids'][0];
        $last_fid = $this->CurrentFont['subsetfontids'][0];
        foreach ($unicode as $c) {
            /*  // mPDF 6 deleted
              if ($c == 7 || $c == 8) {
              if ($orig_fid != $last_fid) {
              $ret .= '> Tj /F'.$orig_fid.' '.$this->FontSizePt.' Tf <';
              $last_fid = $orig_fid;
              }
              if ($c == 7) { $ret .= $this->aliasNbPgHex; }
              else { $ret .= $this->aliasNbPgGpHex; }
              continue;
              }
             */
            if (!$this->_charDefined($this->CurrentFont['cw'], $c)) {
                $c = 0;
            } // mPDF 6
            for ($i = 0; $i < 99; $i++) {
                // return c as decimal char
                $init = array_search($c, $this->CurrentFont['subsets'][$i]);
                if ($init !== false) {
                    if ($this->CurrentFont['subsetfontids'][$i] != $last_fid) {
                        $ret .= '> Tj /F' . $this->CurrentFont['subsetfontids'][$i] . ' ' . $this->FontSizePt . ' Tf <';
                        $last_fid = $this->CurrentFont['subsetfontids'][$i];
                    }
                    $ret .= sprintf("%02s", strtoupper(dechex($init)));
                    break;
                } // TrueType embedded SUBSETS
                elseif (count($this->CurrentFont['subsets'][$i]) < 255) {
                    $n = count($this->CurrentFont['subsets'][$i]);
                    $this->CurrentFont['subsets'][$i][$n] = $c;
                    if ($this->CurrentFont['subsetfontids'][$i] != $last_fid) {
                        $ret .= '> Tj /F' . $this->CurrentFont['subsetfontids'][$i] . ' ' . $this->FontSizePt . ' Tf <';
                        $last_fid = $this->CurrentFont['subsetfontids'][$i];
                    }
                    $ret .= sprintf("%02s", strtoupper(dechex($n)));
                    break;
                } elseif (!isset($this->CurrentFont['subsets'][($i + 1)])) {
                    // TrueType embedded SUBSETS
                    $this->CurrentFont['subsets'][($i + 1)] = [0 => 0];
                    $new_fid = count($this->fonts) + $this->extraFontSubsets + 1;
                    $this->CurrentFont['subsetfontids'][($i + 1)] = $new_fid;
                    $this->extraFontSubsets++;
                }
            }
        }
        $ret .= '>';
        if ($last_fid != $orig_fid) {
            $ret .= ' Tj /F' . $orig_fid . ' ' . $this->FontSizePt . ' Tf <> ';
        }
        return $ret;
    }

    // Converts UTF-8 strings to UTF16-BE.
    public function UTF8ToUTF16BE($str, $setbom = true)
    {
        if ($this->checkSIP && preg_match("/([\x{20000}-\x{2FFFF}])/u", $str)) {
            if (!in_array($this->currentfontfamily, ['gb', 'big5', 'sjis', 'uhc', 'gbB', 'big5B', 'sjisB', 'uhcB', 'gbI', 'big5I', 'sjisI', 'uhcI',
                    'gbBI', 'big5BI', 'sjisBI', 'uhcBI'])) {
                $str = preg_replace("/[\x{20000}-\x{2FFFF}]/u", chr(0), $str);
            }
        }
        if ($this->checkSMP && preg_match("/([\x{10000}-\x{1FFFF}])/u", $str)) {
            $str = preg_replace("/[\x{10000}-\x{1FFFF}]/u", chr(0), $str);
        }
        $outstr = ""; // string to be returned
        if ($setbom) {
            $outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
        }
        $outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
        return $outstr;
    }

    /* -- CJK-FONTS -- */

    // from class PDF_Chinese CJK EXTENSIONS
    public function AddCIDFont($family, $style, $name, &$cw, $CMap, $registry, $desc)
    {
        $fontkey = strtolower($family) . strtoupper($style);
        if (isset($this->fonts[$fontkey])) {
            throw new \Mpdf\MpdfException("Font already added: $family $style");
        }
        $i = count($this->fonts) + $this->extraFontSubsets + 1;
        $name = str_replace(' ', '', $name);
        if ($family == 'sjis') {
            $up = -120;
        } else {
            $up = -130;
        }
        // ? 'up' and 'ut' do not seem to be referenced anywhere
        $this->fonts[$fontkey] = ['i' => $i, 'type' => 'Type0', 'name' => $name, 'up' => $up, 'ut' => 40, 'cw' => $cw, 'CMap' => $CMap, 'registry' => $registry, 'MissingWidth' => 1000, 'desc' => $desc];
    }

    public function AddCJKFont($family)
    {

        if ($this->PDFA || $this->PDFX) {
            throw new \Mpdf\MpdfException("Adobe CJK fonts cannot be embedded in mPDF (required for PDFA1-b and PDFX/1-a).");
        }
        if ($family == 'big5') {
            $this->AddBig5Font();
        } elseif ($family == 'gb') {
            $this->AddGBFont();
        } elseif ($family == 'sjis') {
            $this->AddSJISFont();
        } elseif ($family == 'uhc') {
            $this->AddUHCFont();
        }
    }

    public function AddBig5Font()
    {
        // Add Big5 font with proportional Latin
        $family = 'big5';
        $name = 'MSungStd-Light-Acro';
        $cw = $this->Big5_widths;
        $CMap = 'UniCNS-UTF16-H';
        $registry = ['ordering' => 'CNS1', 'supplement' => 4];
        $desc = [
            'Ascent' => 880,
            'Descent' => -120,
            'CapHeight' => 880,
            'Flags' => 6,
            'FontBBox' => '[-160 -249 1015 1071]',
            'ItalicAngle' => 0,
            'StemV' => 93,
        ];
        $this->AddCIDFont($family, '', $name, $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'B', $name . ',Bold', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'I', $name . ',Italic', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'BI', $name . ',BoldItalic', $cw, $CMap, $registry, $desc);
    }

    public function AddGBFont()
    {
        // Add GB font with proportional Latin
        $family = 'gb';
        $name = 'STSongStd-Light-Acro';
        $cw = $this->GB_widths;
        $CMap = 'UniGB-UTF16-H';
        $registry = ['ordering' => 'GB1', 'supplement' => 4];
        $desc = [
            'Ascent' => 880,
            'Descent' => -120,
            'CapHeight' => 737,
            'Flags' => 6,
            'FontBBox' => '[-25 -254 1000 880]',
            'ItalicAngle' => 0,
            'StemV' => 58,
            'Style' => '<< /Panose <000000000400000000000000> >>',
        ];
        $this->AddCIDFont($family, '', $name, $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'B', $name . ',Bold', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'I', $name . ',Italic', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'BI', $name . ',BoldItalic', $cw, $CMap, $registry, $desc);
    }

    public function AddSJISFont()
    {
        // Add SJIS font with proportional Latin
        $family = 'sjis';
        $name = 'KozMinPro-Regular-Acro';
        $cw = $this->SJIS_widths;
        $CMap = 'UniJIS-UTF16-H';
        $registry = ['ordering' => 'Japan1', 'supplement' => 5];
        $desc = [
            'Ascent' => 880,
            'Descent' => -120,
            'CapHeight' => 740,
            'Flags' => 6,
            'FontBBox' => '[-195 -272 1110 1075]',
            'ItalicAngle' => 0,
            'StemV' => 86,
            'XHeight' => 502,
        ];
        $this->AddCIDFont($family, '', $name, $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'B', $name . ',Bold', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'I', $name . ',Italic', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'BI', $name . ',BoldItalic', $cw, $CMap, $registry, $desc);
    }

    public function AddUHCFont()
    {
        // Add UHC font with proportional Latin
        $family = 'uhc';
        $name = 'HYSMyeongJoStd-Medium-Acro';
        $cw = $this->UHC_widths;
        $CMap = 'UniKS-UTF16-H';
        $registry = ['ordering' => 'Korea1', 'supplement' => 2];
        $desc = [
            'Ascent' => 880,
            'Descent' => -120,
            'CapHeight' => 720,
            'Flags' => 6,
            'FontBBox' => '[-28 -148 1001 880]',
            'ItalicAngle' => 0,
            'StemV' => 60,
            'Style' => '<< /Panose <000000000600000000000000> >>',
        ];
        $this->AddCIDFont($family, '', $name, $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'B', $name . ',Bold', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'I', $name . ',Italic', $cw, $CMap, $registry, $desc);
        $this->AddCIDFont($family, 'BI', $name . ',BoldItalic', $cw, $CMap, $registry, $desc);
    }

    /* -- END CJK-FONTS -- */

    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////

    public function SetDefaultFont($font)
    {
        // Disallow embedded fonts to be used as defaults in PDFA
        if ($this->PDFA || $this->PDFX) {
            if (strtolower($font) == 'ctimes') {
                $font = 'serif';
            }
            if (strtolower($font) == 'ccourier') {
                $font = 'monospace';
            }
            if (strtolower($font) == 'chelvetica') {
                $font = 'sans-serif';
            }
        }
        $font = $this->SetFont($font); // returns substituted font if necessary
        $this->default_font = $font;
        $this->original_default_font = $font;
        if (!$this->watermark_font) {
            $this->watermark_font = $font;
        } // *WATERMARK*
        $this->defaultCSS['BODY']['FONT-FAMILY'] = $font;
        $this->cssManager->CSS['BODY']['FONT-FAMILY'] = $font;
    }

    public function SetDefaultFontSize($fontsize)
    {
        $this->default_font_size = $fontsize;
        $this->original_default_font_size = $fontsize;
        $this->SetFontSize($fontsize);
        $this->defaultCSS['BODY']['FONT-SIZE'] = $fontsize . 'pt';
        $this->cssManager->CSS['BODY']['FONT-SIZE'] = $fontsize . 'pt';
    }

    public function SetDefaultBodyCSS($prop, $val)
    {
        if ($prop) {
            $this->defaultCSS['BODY'][strtoupper($prop)] = $val;
            $this->cssManager->CSS['BODY'][strtoupper($prop)] = $val;
        }
    }

    public function SetDirectionality($dir = 'ltr')
    {
        /* -- OTL -- */
        if (strtolower($dir) == 'rtl') {
            if ($this->directionality != 'rtl') {
                // Swop L/R Margins so page 1 RTL is an 'even' page
                $tmp = $this->DeflMargin;
                $this->DeflMargin = $this->DefrMargin;
                $this->DefrMargin = $tmp;
                $this->orig_lMargin = $this->DeflMargin;
                $this->orig_rMargin = $this->DefrMargin;

                $this->SetMargins($this->DeflMargin, $this->DefrMargin, $this->tMargin);
            }
            $this->directionality = 'rtl';
            $this->defaultAlign = 'R';
            $this->defaultTableAlign = 'R';
        } else {
            /* -- END OTL -- */
            $this->directionality = 'ltr';
            $this->defaultAlign = 'L';
            $this->defaultTableAlign = 'L';
        } // *OTL*
        $this->cssManager->CSS['BODY']['DIRECTION'] = $this->directionality;
    }

    // Return either a number (factor) - based on current set fontsize (if % or em) - or exact lineheight (with 'mm' after it)
    public function fixLineheight($v)
    {
        $lh = false;
        if (preg_match('/^[0-9\.,]*$/', $v) && $v >= 0) {
            return ($v + 0);
        } elseif (strtoupper($v) == 'NORMAL' || $v == 'N') {
            return 'N';  // mPDF 6
        } else {
            $tlh = $this->sizeConverter->convert($v, $this->FontSize, $this->FontSize, true);
            if ($tlh) {
                return ($tlh . 'mm');
            }
        }
        return $this->normalLineheight;
    }

    // Set a (fixed) lineheight to an actual value - either to named fontsize(pts) or default
    public function SetLineHeight($FontPt = '', $lh = '')
    {
        if (!$FontPt) {
            $FontPt = $this->FontSizePt;
        }
        $fs = $FontPt / Mpdf::SCALE;
        $this->lineheight = $this->_computeLineheight($lh, $fs);
    }

    public function SetBasePath($str = '')
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        } else {
            $host = '';
        }
        if (!$str) {
            if ($_SERVER['SCRIPT_NAME']) {
                $currentPath = dirname($_SERVER['SCRIPT_NAME']);
            } else {
                $currentPath = dirname($_SERVER['PHP_SELF']);
            }
            $currentPath = str_replace("\\", "/", $currentPath);
            if ($currentPath == '/') {
                $currentPath = '';
            }
            if ($host) {  // mPDF 6
                if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off') {
                    $currpath = 'https://' . $host . $currentPath . '/';
                } else {
                    $currpath = 'http://' . $host . $currentPath . '/';
                }
            } else {
                $currpath = '';
            }
            $this->basepath = $currpath;
            $this->basepathIsLocal = true;
            return;
        }
        $str = preg_replace('/\?.*/', '', $str);
        if (!preg_match('/(http|https|ftp):\/\/.*\//i', $str)) {
            $str .= '/';
        }
        $str .= 'xxx'; // in case $str ends in / e.g. http://www.bbc.co.uk/
        $this->basepath = dirname($str) . "/"; // returns e.g. e.g. http://www.google.com/dir1/dir2/dir3/
        $this->basepath = str_replace("\\", "/", $this->basepath); // If on Windows
        $tr = parse_url($this->basepath);
        if (isset($tr['host']) && ($tr['host'] == $host)) {
            $this->basepathIsLocal = true;
        } else {
            $this->basepathIsLocal = false;
        }
    }

    public function GetFullPath(&$path, $basepath = '')
    {
        // When parsing CSS need to pass temporary basepath - so links are relative to current stylesheet
        if (!$basepath) {
            $basepath = $this->basepath;
        }

        // Fix path value
        $path = str_replace("\\", '/', $path); // If on Windows

        // mPDF 5.7.2
        if (substr($path, 0, 2) === '//') {
            $scheme = parse_url($basepath, PHP_URL_SCHEME);
            $scheme = $scheme ?: 'http';
            $path = $scheme . ':' . $path;
        }

        $path = preg_replace('|^./|', '', $path); // Inadvertently corrects "./path/etc" and "//www.domain.com/etc"

        if (substr($path, 0, 1) == '#') {
            return;
        }

        if (preg_match('@^(mailto|tel|fax):.*@i', $path)) {
            return;
        }

        if (substr($path, 0, 3) == "../") { // It is a relative link

            $backtrackamount = substr_count($path, "../");
            $maxbacktrack = substr_count($basepath, "/") - 3;
            $filepath = str_replace("../", '', $path);
            $path = $basepath;

            // If it is an invalid relative link, then make it go to directory root
            if ($backtrackamount > $maxbacktrack) {
                $backtrackamount = $maxbacktrack;
            }

            // Backtrack some directories
            for ($i = 0; $i < $backtrackamount + 1; $i++) {
                $path = substr($path, 0, strrpos($path, "/"));
            }

            $path = $path . "/" . $filepath; // Make it an absolute path

        } elseif ((strpos($path, ":/") === false || strpos($path, ":/") > 10) && !is_file($path)) { // It is a local link

            if (substr($path, 0, 1) == "/") {

                $tr = parse_url($basepath);

                // mPDF 5.7.2
                $root = '';
                if (!empty($tr['scheme'])) {
                    $root .= $tr['scheme'] . '://';
                }

                $root .= isset($tr['host']) ? $tr['host'] : '';
                $root .= ((isset($tr['port']) && $tr['port']) ? (':' . $tr['port']) : ''); // mPDF 5.7.3

                $path = $root . $path;

            } else {
                $path = $basepath . $path;
            }
        }
        // Do nothing if it is an Absolute Link
    }

    public function docPageNum($num = 0, $extras = false)
    {
        if ($num < 1) {
            $num = $this->page;
        }

        $type = $this->defaultPageNumStyle; // set default Page Number Style
        $ppgno = $num;
        $suppress = 0;
        $offset = 0;
        $lastreset = 0;

        foreach ($this->PageNumSubstitutions as $psarr) {
            if ($num >= $psarr['from']) {
                if ($psarr['reset']) {
                    if ($psarr['reset'] > 1) {
                        $offset = $psarr['reset'] - 1;
                    }
                    $ppgno = $num - $psarr['from'] + 1 + $offset;
                    $lastreset = $psarr['from'];
                }
                if ($psarr['type']) {
                    $type = $psarr['type'];
                }
                if (strtoupper($psarr['suppress']) == 'ON' || $psarr['suppress'] == 1) {
                    $suppress = 1;
                } elseif (strtoupper($psarr['suppress']) == 'OFF') {
                    $suppress = 0;
                }
            }
        }

        if ($suppress) {
            return '';
        }

        $ppgno = $this->_getStyledNumber($ppgno, $type);

        if ($extras) {
            $ppgno = $this->pagenumPrefix . $ppgno . $this->pagenumSuffix;
        }

        return $ppgno;
    }

    public function docPageNumTotal($num = 0, $extras = false)
    {
        if ($num < 1) {
            $num = $this->page;
        }

        $type = $this->defaultPageNumStyle; // set default Page Number Style
        $ppgstart = 1;
        $ppgend = count($this->pages) + 1;
        $suppress = 0;
        $offset = 0;

        foreach ($this->PageNumSubstitutions as $psarr) {
            if ($num >= $psarr['from']) {
                if ($psarr['reset']) {
                    if ($psarr['reset'] > 1) {
                        $offset = $psarr['reset'] - 1;
                    }
                    $ppgstart = $psarr['from'] + $offset;
                    $ppgend = count($this->pages) + 1 + $offset;
                }
                if ($psarr['type']) {
                    $type = $psarr['type'];
                }
                if (strtoupper($psarr['suppress']) == 'ON' || $psarr['suppress'] == 1) {
                    $suppress = 1;
                } elseif (strtoupper($psarr['suppress']) == 'OFF') {
                    $suppress = 0;
                }
            }
            if ($num < $psarr['from']) {
                if ($psarr['reset']) {
                    $ppgend = $psarr['from'] + $offset;
                    break;
                }
            }
        }

        if ($suppress) {
            return '';
        }

        $ppgno = $ppgend - $ppgstart + $offset;
        $ppgno = $this->_getStyledNumber($ppgno, $type);

        if ($extras) {
            $ppgno = $this->pagenumPrefix . $ppgno . $this->pagenumSuffix;
        }

        return $ppgno;
    }

    public function docPageSettings($num = 0)
    {
        // Returns current type (numberstyle), suppression state for this page number;
        // reset is only returned if set for this page number
        if ($num < 1) {
            $num = $this->page;
        }

        $type = $this->defaultPageNumStyle; // set default Page Number Style
        $ppgno = $num;
        $suppress = 0;
        $offset = 0;
        $reset = '';

        foreach ($this->PageNumSubstitutions as $psarr) {
            if ($num >= $psarr['from']) {
                if ($psarr['reset']) {
                    if ($psarr['reset'] > 1) {
                        $offset = $psarr['reset'] - 1;
                    }
                    $ppgno = $num - $psarr['from'] + 1 + $offset;
                }
                if ($psarr['type']) {
                    $type = $psarr['type'];
                }
                if (strtoupper($psarr['suppress']) == 'ON' || $psarr['suppress'] == 1) {
                    $suppress = 1;
                } elseif (strtoupper($psarr['suppress']) == 'OFF') {
                    $suppress = 0;
                }
            }
            if ($num == $psarr['from']) {
                $reset = $psarr['reset'];
            }
        }

        if ($suppress) {
            $suppress = 'on';
        } else {
            $suppress = 'off';
        }

        return [$type, $suppress, $reset];
    }

    public function RestartDocTemplate()
    {
        $this->docTemplateStart = $this->page;
    }

    // Page header
    public function Header($content = '')
    {

        $this->cMarginL = 0;
        $this->cMarginR = 0;


        if (($this->mirrorMargins && ($this->page % 2 == 0) && $this->HTMLHeaderE) || ($this->mirrorMargins && ($this->page % 2 == 1) && $this->HTMLHeader) || (!$this->mirrorMargins && $this->HTMLHeader)) {
            $this->writeHTMLHeaders();
            return;
        }
    }

    /* -- TABLES -- */
    public function TableHeaderFooter($content = '', $tablestartpage = '', $tablestartcolumn = '', $horf = 'H', $level = 0, $firstSpread = true, $finalSpread = true)
    {
        if (($horf == 'H' || $horf == 'F') && !empty($content)) { // mPDF 5.7.2
            $table = &$this->table[1][1];

            // mPDF 5.7.2
            if ($horf == 'F') { // Table Footer
                $firstrow = count($table['cells']) - $table['footernrows'];
                $lastrow = count($table['cells']) - 1;
            } else {  // Table Header
                $firstrow = 0;
                $lastrow = $table['headernrows'] - 1;
            }
            if (empty($content[$firstrow])) {
                if ($this->debug) {
                    throw new \Mpdf\MpdfException("<tfoot> must precede <tbody> in a table");
                } else {
                    return;
                }
            }


            // Advance down page by half width of top border
            if ($horf == 'H') { // Only if header
                if ($table['borders_separate']) {
                    $adv = $table['border_spacing_V'] / 2 + $table['border_details']['T']['w'] + $table['padding']['T'];
                } else {
                    $adv = $table['max_cell_border_width']['T'] / 2;
                }
                if ($adv) {
                    if ($this->table_rotate) {
                        $this->y += ($adv);
                    } else {
                        $this->DivLn($adv, $this->blklvl, true);
                    }
                }
            }

            $topy = $content[$firstrow][0]['y'] - $this->y;

            for ($i = $firstrow; $i <= $lastrow; $i++) {
                $y = $this->y;

                /* -- COLUMNS -- */
                // If outside columns, this is done in PaintDivBB
                if ($this->ColActive) {
                    // OUTER FILL BGCOLOR of DIVS
                    if ($this->blklvl > 0) {
                        $firstblockfill = $this->GetFirstBlockFill();
                        if ($firstblockfill && $this->blklvl >= $firstblockfill) {
                            $divh = $content[$i][0]['h'];
                            $bak_x = $this->x;
                            $this->DivLn($divh, -3, false);
                            // Reset current block fill
                            $bcor = $this->blk[$this->blklvl]['bgcolorarray'];
                            $this->SetFColor($bcor);
                            $this->x = $bak_x;
                        }
                    }
                }
                /* -- END COLUMNS -- */

                $colctr = 0;
                foreach ($content[$i] as $tablehf) {
                    $colctr++;
                    $y = Arrays::get($tablehf, 'y', null) - $topy;
                    $this->y = $y;
                    // Set some cell values
                    $x = Arrays::get($tablehf, 'x', null);
                    if (($this->mirrorMargins) && ($tablestartpage == 'ODD') && (($this->page) % 2 == 0)) { // EVEN
                        $x = $x + $this->MarginCorrection;
                    } elseif (($this->mirrorMargins) && ($tablestartpage == 'EVEN') && (($this->page) % 2 == 1)) { // ODD
                        $x = $x + $this->MarginCorrection;
                    }
                    /* -- COLUMNS -- */
                    // Added to correct for Columns
                    if ($this->ColActive) {
                        if ($this->directionality == 'rtl') { // *OTL*
                            $x -= ($this->CurrCol - $tablestartcolumn) * ($this->ColWidth + $this->ColGap); // *OTL*
                        } // *OTL*
                        else { // *OTL*
                            $x += ($this->CurrCol - $tablestartcolumn) * ($this->ColWidth + $this->ColGap);
                        } // *OTL*
                    }
                    /* -- END COLUMNS -- */

                    if ($colctr == 1) {
                        $x0 = $x;
                    }

                    // mPDF ITERATION
                    if ($this->iterationCounter) {
                        foreach ($tablehf['textbuffer'] as $k => $t) {
                            if (!is_array($t[0]) && preg_match('/{iteration ([a-zA-Z0-9_]+)}/', $t[0], $m)) {
                                $vname = '__' . $m[1] . '_';
                                if (!isset($this->$vname)) {
                                    $this->$vname = 1;
                                } else {
                                    $this->$vname++;
                                }
                                $tablehf['textbuffer'][$k][0] = preg_replace('/{iteration ' . $m[1] . '}/', $this->$vname, $tablehf['textbuffer'][$k][0]);
                            }
                        }
                    }

                    $w = Arrays::get($tablehf, 'w', null);
                    $h = Arrays::get($tablehf, 'h', null);
                    $va = Arrays::get($tablehf, 'va', null);
                    $R = Arrays::get($tablehf, 'R', null);
                    $direction = Arrays::get($tablehf, 'direction', null);
                    $mih = Arrays::get($tablehf, 'mih', null);
                    $border = Arrays::get($tablehf, 'border', null);
                    $border_details = Arrays::get($tablehf, 'border_details', null);
                    $padding = Arrays::get($tablehf, 'padding', null);
                    $this->tabletheadjustfinished = true;

                    $textbuffer = Arrays::get($tablehf, 'textbuffer', null);

                    // Align
                    $align = Arrays::get($tablehf, 'a', null);
                    $this->cellTextAlign = $align;

                    $this->cellLineHeight = Arrays::get($tablehf, 'cellLineHeight', null);
                    $this->cellLineStackingStrategy = Arrays::get($tablehf, 'cellLineStackingStrategy', null);
                    $this->cellLineStackingShift = Arrays::get($tablehf, 'cellLineStackingShift', null);

                    $this->x = $x;

                    if ($this->ColActive) {
                        if ($table['borders_separate']) {
                            $tablefill = isset($table['bgcolor'][-1]) ? $table['bgcolor'][-1] : 0;
                            if ($tablefill) {
                                $color = $this->colorConverter->convert($tablefill, $this->PDFAXwarnings);
                                if ($color) {
                                    $xadj = ($table['border_spacing_H'] / 2);
                                    $yadj = ($table['border_spacing_V'] / 2);
                                    $wadj = $table['border_spacing_H'];
                                    $hadj = $table['border_spacing_V'];
                                    if ($i == $firstrow && $horf == 'H') {  // Top
                                        $yadj += $table['padding']['T'] + $table['border_details']['T']['w'];
                                        $hadj += $table['padding']['T'] + $table['border_details']['T']['w'];
                                    }
                                    if (($i == ($lastrow) || (isset($tablehf['rowspan']) && ($i + $tablehf['rowspan']) == ($lastrow + 1)) || (!isset($tablehf['rowspan']) && ($i + 1) == ($lastrow + 1))) && $horf == 'F') { // Bottom
                                        $hadj += $table['padding']['B'] + $table['border_details']['B']['w'];
                                    }
                                    if ($colctr == 1) {  // Left
                                        $xadj += $table['padding']['L'] + $table['border_details']['L']['w'];
                                        $wadj += $table['padding']['L'] + $table['border_details']['L']['w'];
                                    }
                                    if ($colctr == count($content[$i])) { // Right
                                        $wadj += $table['padding']['R'] + $table['border_details']['R']['w'];
                                    }
                                    $this->SetFColor($color);
                                    $this->Rect($x - $xadj, $y - $yadj, $w + $wadj, $h + $hadj, 'F');
                                }
                            }
                        }
                    }

                    if ($table['empty_cells'] != 'hide' || !empty($textbuffer) || !$table['borders_separate']) {
                        $paintcell = true;
                    } else {
                        $paintcell = false;
                    }

                    // Vertical align
                    if ($R && intval($R) > 0 && isset($va) && $va != 'B') {
                        $va = 'B';
                    }

                    if (!isset($va) || empty($va) || $va == 'M') {
                        $this->y += ($h - $mih) / 2;
                    } elseif (isset($va) && $va == 'B') {
                        $this->y += $h - $mih;
                    }


                    // TABLE ROW OR CELL FILL BGCOLOR
                    $fill = 0;
                    if (isset($tablehf['bgcolor']) && $tablehf['bgcolor'] && $tablehf['bgcolor'] != 'transparent') {
                        $fill = $tablehf['bgcolor'];
                        $leveladj = 6;
                    } elseif (isset($content[$i][0]['trbgcolor']) && $content[$i][0]['trbgcolor'] && $content[$i][0]['trbgcolor'] != 'transparent') { // Row color
                        $fill = $content[$i][0]['trbgcolor'];
                        $leveladj = 3;
                    }
                    if ($fill && $paintcell) {
                        $color = $this->colorConverter->convert($fill, $this->PDFAXwarnings);
                        if ($color) {
                            if ($table['borders_separate']) {
                                if ($this->ColActive) {
                                    $this->SetFColor($color);
                                    $this->Rect($x + ($table['border_spacing_H'] / 2), $y + ($table['border_spacing_V'] / 2), $w - $table['border_spacing_H'], $h - $table['border_spacing_V'], 'F');
                                } else {
                                    $this->tableBackgrounds[$level * 9 + $leveladj][] = ['gradient' => false, 'x' => ($x + ($table['border_spacing_H'] / 2)), 'y' => ($y + ($table['border_spacing_V'] / 2)), 'w' => ($w - $table['border_spacing_H']), 'h' => ($h - $table['border_spacing_V']), 'col' => $color];
                                }
                            } else {
                                if ($this->ColActive) {
                                    $this->SetFColor($color);
                                    $this->Rect($x, $y, $w, $h, 'F');
                                } else {
                                    $this->tableBackgrounds[$level * 9 + $leveladj][] = ['gradient' => false, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'col' => $color];
                                }
                            }
                        }
                    }


                    /* -- BACKGROUNDS -- */
                    if (isset($tablehf['gradient']) && $tablehf['gradient'] && $paintcell) {
                        $g = $this->gradient->parseBackgroundGradient($tablehf['gradient']);
                        if ($g) {
                            if ($table['borders_separate']) {
                                $px = $x + ($table['border_spacing_H'] / 2);
                                $py = $y + ($table['border_spacing_V'] / 2);
                                $pw = $w - $table['border_spacing_H'];
                                $ph = $h - $table['border_spacing_V'];
                            } else {
                                $px = $x;
                                $py = $y;
                                $pw = $w;
                                $ph = $h;
                            }
                            if ($this->ColActive) {
                                $this->gradient->Gradient($px, $py, $pw, $ph, $g['type'], $g['stops'], $g['colorspace'], $g['coords'], $g['extend']);
                            } else {
                                $this->tableBackgrounds[$level * 9 + 7][] = ['gradient' => true, 'x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
                            }
                        }
                    }

                    if (isset($tablehf['background-image']) && $paintcell) {
                        if ($tablehf['background-image']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $tablehf['background-image']['gradient'])) {
                            $g = $this->gradient->parseMozGradient($tablehf['background-image']['gradient']);
                            if ($g) {
                                if ($table['borders_separate']) {
                                    $px = $x + ($table['border_spacing_H'] / 2);
                                    $py = $y + ($table['border_spacing_V'] / 2);
                                    $pw = $w - $table['border_spacing_H'];
                                    $ph = $h - $table['border_spacing_V'];
                                } else {
                                    $px = $x;
                                    $py = $y;
                                    $pw = $w;
                                    $ph = $h;
                                }
                                if ($this->ColActive) {
                                    $this->gradient->Gradient($px, $py, $pw, $ph, $g['type'], $g['stops'], $g['colorspace'], $g['coords'], $g['extend']);
                                } else {
                                    $this->tableBackgrounds[$level * 9 + 7][] = ['gradient' => true, 'x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
                                }
                            }
                        } elseif ($tablehf['background-image']['image_id']) { // Background pattern
                            $n = count($this->patterns) + 1;
                            if ($table['borders_separate']) {
                                $px = $x + ($table['border_spacing_H'] / 2);
                                $py = $y + ($table['border_spacing_V'] / 2);
                                $pw = $w - $table['border_spacing_H'];
                                $ph = $h - $table['border_spacing_V'];
                            } else {
                                $px = $x;
                                $py = $y;
                                $pw = $w;
                                $ph = $h;
                            }
                            if ($this->ColActive) {
                                list($orig_w, $orig_h, $x_repeat, $y_repeat) = $this->_resizeBackgroundImage($tablehf['background-image']['orig_w'], $tablehf['background-image']['orig_h'], $pw, $ph, $tablehf['background-image']['resize'], $tablehf['background-image']['x_repeat'], $tablehf['background-image']['y_repeat']);
                                $this->patterns[$n] = ['x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'pgh' => $this->h, 'image_id' => $tablehf['background-image']['image_id'], 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $tablehf['background-image']['x_pos'], 'y_pos' => $tablehf['background-image']['y_pos'], 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'itype' => $tablehf['background-image']['itype']];
                                if ($tablehf['background-image']['opacity'] > 0 && $tablehf['background-image']['opacity'] < 1) {
                                    $opac = $this->SetAlpha($tablehf['background-image']['opacity'], 'Normal', true);
                                } else {
                                    $opac = '';
                                }
                                $this->_out(sprintf('q /Pattern cs /P%d scn %s %.3F %.3F %.3F %.3F re f Q', $n, $opac, $px * Mpdf::SCALE, ($this->h - $py) * Mpdf::SCALE, $pw * Mpdf::SCALE, -$ph * Mpdf::SCALE));
                            } else {
                                $this->tableBackgrounds[$level * 9 + 8][] = ['x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'image_id' => $tablehf['background-image']['image_id'], 'orig_w' => $tablehf['background-image']['orig_w'], 'orig_h' => $tablehf['background-image']['orig_h'], 'x_pos' => $tablehf['background-image']['x_pos'], 'y_pos' => $tablehf['background-image']['y_pos'], 'x_repeat' => $tablehf['background-image']['x_repeat'], 'y_repeat' => $tablehf['background-image']['y_repeat'], 'clippath' => '', 'resize' => $tablehf['background-image']['resize'], 'opacity' => $tablehf['background-image']['opacity'], 'itype' => $tablehf['background-image']['itype']];
                            }
                        }
                    }
                    /* -- END BACKGROUNDS -- */

                    // Cell Border
                    if ($table['borders_separate'] && $paintcell && $border) {
                        $this->_tableRect($x + ($table['border_spacing_H'] / 2) + ($border_details['L']['w'] / 2), $y + ($table['border_spacing_V'] / 2) + ($border_details['T']['w'] / 2), $w - $table['border_spacing_H'] - ($border_details['L']['w'] / 2) - ($border_details['R']['w'] / 2), $h - $table['border_spacing_V'] - ($border_details['T']['w'] / 2) - ($border_details['B']['w'] / 2), $border, $border_details, false, $table['borders_separate']);
                    } elseif ($paintcell && $border) {
                        $this->_tableRect($x, $y, $w, $h, $border, $border_details, true, $table['borders_separate']);   // true causes buffer
                    }

                    // Print cell content
                    if (!empty($textbuffer)) {
                        if ($horf == 'F' && preg_match('/{colsum([0-9]*)[_]*}/', $textbuffer[0][0], $m)) {
                            $rep = sprintf("%01." . intval($m[1]) . "f", $this->colsums[$colctr - 1]);
                            $textbuffer[0][0] = preg_replace('/{colsum[0-9_]*}/', $rep, $textbuffer[0][0]);
                        }

                        if ($R) {
                            $cellPtSize = $textbuffer[0][11] / $this->shrin_k;
                            if (!$cellPtSize) {
                                $cellPtSize = $this->default_font_size;
                            }
                            $cellFontHeight = ($cellPtSize / Mpdf::SCALE);
                            $opx = $this->x;
                            $opy = $this->y;
                            $angle = intval($R);

                            // Only allow 45 - 90 degrees (when bottom-aligned) or -90
                            if ($angle > 90) {
                                $angle = 90;
                            } elseif ($angle > 0 && (isset($va) && $va != 'B')) {
                                $angle = 90;
                            } elseif ($angle > 0 && $angle < 45) {
                                $angle = 45;
                            } elseif ($angle < 0) {
                                $angle = -90;
                            }

                            $offset = ((sin(deg2rad($angle))) * 0.37 * $cellFontHeight);
                            if (isset($align) && $align == 'R') {
                                $this->x += ($w) + ($offset) - ($cellFontHeight / 3) - ($padding['R'] + $border_details['R']['w']);
                            } elseif (!isset($align) || $align == 'C') {
                                $this->x += ($w / 2) + ($offset);
                            } else {
                                $this->x += ($offset) + ($cellFontHeight / 3) + ($padding['L'] + $border_details['L']['w']);
                            }
                            $str = '';
                            foreach ($tablehf['textbuffer'] as $t) {
                                $str .= $t[0] . ' ';
                            }
                            $str = rtrim($str);

                            if (!isset($va) || $va == 'M') {
                                $this->y -= ($h - $mih) / 2; // Undo what was added earlier VERTICAL ALIGN
                                if ($angle > 0) {
                                    $this->y += (($h - $mih) / 2) + ($padding['T'] + $border_details['T']['w']) + ($mih - ($padding['T'] + $border_details['T']['w'] + $border_details['B']['w'] + $padding['B']));
                                } elseif ($angle < 0) {
                                    $this->y += (($h - $mih) / 2) + ($padding['T'] + $border_details['T']['w']);
                                }
                            } elseif (isset($va) && $va == 'B') {
                                $this->y -= $h - $mih; // Undo what was added earlier VERTICAL ALIGN
                                if ($angle > 0) {
                                    $this->y += $h - ($border_details['B']['w'] + $padding['B']);
                                } elseif ($angle < 0) {
                                    $this->y += $h - $mih + ($padding['T'] + $border_details['T']['w']);
                                }
                            } elseif (isset($va) && $va == 'T') {
                                if ($angle > 0) {
                                    $this->y += $mih - ($border_details['B']['w'] + $padding['B']);
                                } elseif ($angle < 0) {
                                    $this->y += ($padding['T'] + $border_details['T']['w']);
                                }
                            }

                            $this->Rotate($angle, $this->x, $this->y);
                            $s_fs = $this->FontSizePt;
                            $s_f = $this->FontFamily;
                            $s_st = $this->FontStyle;
                            if (!empty($textbuffer[0][3])) { // Font Color
                                $cor = $textbuffer[0][3];
                                $this->SetTColor($cor);
                            }
                            $this->SetFont($textbuffer[0][4], $textbuffer[0][2], $cellPtSize, true, true);

                            $this->magic_reverse_dir($str, $this->directionality, $textbuffer[0][18]);
                            $this->Text($this->x, $this->y, $str, $textbuffer[0][18], $textbuffer[0][8]); // textvar
                            $this->Rotate(0);
                            $this->SetFont($s_f, $s_st, $s_fs, true, true);
                            $this->SetTColor(0);
                            $this->x = $opx;
                            $this->y = $opy;
                        } else {
                            if ($table['borders_separate']) { // NB twice border width
                                $xadj = $border_details['L']['w'] + $padding['L'] + ($table['border_spacing_H'] / 2);
                                $wadj = $border_details['L']['w'] + $border_details['R']['w'] + $padding['L'] + $padding['R'] + $table['border_spacing_H'];
                                $yadj = $border_details['T']['w'] + $padding['T'] + ($table['border_spacing_H'] / 2);
                            } else {
                                $xadj = $border_details['L']['w'] / 2 + $padding['L'];
                                $wadj = ($border_details['L']['w'] + $border_details['R']['w']) / 2 + $padding['L'] + $padding['R'];
                                $yadj = $border_details['T']['w'] / 2 + $padding['T'];
                            }

                            $this->divwidth = $w - ($wadj);
                            $this->x += $xadj;
                            $this->y += $yadj;
                            $this->printbuffer($textbuffer, '', true, false, $direction);
                        }
                    }
                    $textbuffer = [];

                    /* -- BACKGROUNDS -- */
                    if (!$this->ColActive) {
                        if (isset($content[$i][0]['trgradients']) && ($colctr == 1 || $table['borders_separate'])) {
                            $g = $this->gradient->parseBackgroundGradient($content[$i][0]['trgradients']);
                            if ($g) {
                                $gx = $x0;
                                $gy = $y;
                                $gh = $h;
                                $gw = $table['w'] - ($table['max_cell_border_width']['L'] / 2) - ($table['max_cell_border_width']['R'] / 2) - $table['margin']['L'] - $table['margin']['R'];
                                if ($table['borders_separate']) {
                                    $gw -= ($table['padding']['L'] + $table['border_details']['L']['w'] + $table['padding']['R'] + $table['border_details']['R']['w'] + $table['border_spacing_H']);
                                    $clx = $x + ($table['border_spacing_H'] / 2);
                                    $cly = $y + ($table['border_spacing_V'] / 2);
                                    $clw = $w - $table['border_spacing_H'];
                                    $clh = $h - $table['border_spacing_V'];
                                    // Set clipping path
                                    $s = $this->_setClippingPath($clx, $cly, $clw, $clh); // mPDF 6
                                    $this->tableBackgrounds[$level * 9 + 4][] = ['gradient' => true, 'x' => $gx + ($table['border_spacing_H'] / 2), 'y' => $gy + ($table['border_spacing_V'] / 2), 'w' => $gw - $table['border_spacing_V'], 'h' => $gh - $table['border_spacing_H'], 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => $s];
                                } else {
                                    $this->tableBackgrounds[$level * 9 + 4][] = ['gradient' => true, 'x' => $gx, 'y' => $gy, 'w' => $gw, 'h' => $gh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
                                }
                            }
                        }

                        if (isset($content[$i][0]['trbackground-images']) && ($colctr == 1 || $table['borders_separate'])) {
                            if ($content[$i][0]['trbackground-images']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $content[$i][0]['trbackground-images']['gradient'])) {
                                $g = $this->gradient->parseMozGradient($content[$i][0]['trbackground-images']['gradient']);
                                if ($g) {
                                    $gx = $x0;
                                    $gy = $y;
                                    $gh = $h;
                                    $gw = $table['w'] - ($table['max_cell_border_width']['L'] / 2) - ($table['max_cell_border_width']['R'] / 2) - $table['margin']['L'] - $table['margin']['R'];
                                    if ($table['borders_separate']) {
                                        $gw -= ($table['padding']['L'] + $table['border_details']['L']['w'] + $table['padding']['R'] + $table['border_details']['R']['w'] + $table['border_spacing_H']);
                                        $clx = $x + ($table['border_spacing_H'] / 2);
                                        $cly = $y + ($table['border_spacing_V'] / 2);
                                        $clw = $w - $table['border_spacing_H'];
                                        $clh = $h - $table['border_spacing_V'];
                                        // Set clipping path
                                        $s = $this->_setClippingPath($clx, $cly, $clw, $clh); // mPDF 6
                                        $this->tableBackgrounds[$level * 9 + 4][] = ['gradient' => true, 'x' => $gx + ($table['border_spacing_H'] / 2), 'y' => $gy + ($table['border_spacing_V'] / 2), 'w' => $gw - $table['border_spacing_V'], 'h' => $gh - $table['border_spacing_H'], 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => $s];
                                    } else {
                                        $this->tableBackgrounds[$level * 9 + 4][] = ['gradient' => true, 'x' => $gx, 'y' => $gy, 'w' => $gw, 'h' => $gh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
                                    }
                                }
                            } else {
                                $image_id = $content[$i][0]['trbackground-images']['image_id'];
                                $orig_w = $content[$i][0]['trbackground-images']['orig_w'];
                                $orig_h = $content[$i][0]['trbackground-images']['orig_h'];
                                $x_pos = $content[$i][0]['trbackground-images']['x_pos'];
                                $y_pos = $content[$i][0]['trbackground-images']['y_pos'];
                                $x_repeat = $content[$i][0]['trbackground-images']['x_repeat'];
                                $y_repeat = $content[$i][0]['trbackground-images']['y_repeat'];
                                $resize = $content[$i][0]['trbackground-images']['resize'];
                                $opacity = $content[$i][0]['trbackground-images']['opacity'];
                                $itype = $content[$i][0]['trbackground-images']['itype'];

                                $clippath = '';
                                $gx = $x0;
                                $gy = $y;
                                $gh = $h;
                                $gw = $table['w'] - ($table['max_cell_border_width']['L'] / 2) - ($table['max_cell_border_width']['R'] / 2) - $table['margin']['L'] - $table['margin']['R'];
                                if ($table['borders_separate']) {
                                    $gw -= ($table['padding']['L'] + $table['border_details']['L']['w'] + $table['padding']['R'] + $table['border_details']['R']['w'] + $table['border_spacing_H']);
                                    $clx = $x + ($table['border_spacing_H'] / 2);
                                    $cly = $y + ($table['border_spacing_V'] / 2);
                                    $clw = $w - $table['border_spacing_H'];
                                    $clh = $h - $table['border_spacing_V'];
                                    // Set clipping path
                                    $s = $this->_setClippingPath($clx, $cly, $clw, $clh); // mPDF 6
                                    $this->tableBackgrounds[$level * 9 + 5][] = ['x' => $gx + ($table['border_spacing_H'] / 2), 'y' => $gy + ($table['border_spacing_V'] / 2), 'w' => $gw - $table['border_spacing_V'], 'h' => $gh - $table['border_spacing_H'], 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => $s, 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype];
                                } else {
                                    $this->tableBackgrounds[$level * 9 + 5][] = ['x' => $gx, 'y' => $gy, 'w' => $gw, 'h' => $gh, 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => '', 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype];
                                }
                            }
                        }
                    }
                    /* -- END BACKGROUNDS -- */

                    // TABLE BORDER - if separate OR collapsed and only table border
                    if (($table['borders_separate'] || ($this->simpleTables && !$table['simple']['border'])) && $table['border']) {
                        $halfspaceL = $table['padding']['L'] + ($table['border_spacing_H'] / 2);
                        $halfspaceR = $table['padding']['R'] + ($table['border_spacing_H'] / 2);
                        $halfspaceT = $table['padding']['T'] + ($table['border_spacing_V'] / 2);
                        $halfspaceB = $table['padding']['B'] + ($table['border_spacing_V'] / 2);
                        $tbx = $x;
                        $tby = $y;
                        $tbw = $w;
                        $tbh = $h;
                        $tab_bord = 0;
                        $corner = '';
                        if ($i == $firstrow && $horf == 'H') {  // Top
                            $tby -= $halfspaceT + ($table['border_details']['T']['w'] / 2);
                            $tbh += $halfspaceT + ($table['border_details']['T']['w'] / 2);
                            $this->setBorder($tab_bord, Border::TOP);
                            $corner .= 'T';
                        }
                        if (($i == ($lastrow) || (isset($tablehf['rowspan']) && ($i + $tablehf['rowspan']) == ($lastrow + 1))) && $horf == 'F') { // Bottom
                            $tbh += $halfspaceB + ($table['border_details']['B']['w'] / 2);
                            $this->setBorder($tab_bord, Border::BOTTOM);
                            $corner .= 'B';
                        }
                        if ($colctr == 1 && $firstSpread) { // Left
                            $tbx -= $halfspaceL + ($table['border_details']['L']['w'] / 2);
                            $tbw += $halfspaceL + ($table['border_details']['L']['w'] / 2);
                            $this->setBorder($tab_bord, Border::LEFT);
                            $corner .= 'L';
                        }
                        if ($colctr == count($content[$i]) && $finalSpread) { // Right
                            $tbw += $halfspaceR + ($table['border_details']['R']['w'] / 2);
                            $this->setBorder($tab_bord, Border::RIGHT);
                            $corner .= 'R';
                        }
                        $this->_tableRect($tbx, $tby, $tbw, $tbh, $tab_bord, $table['border_details'], false, $table['borders_separate'], 'table', $corner, $table['border_spacing_V'], $table['border_spacing_H']);
                    }
                }// end column $content
                $this->y = $y + $h; // Update y coordinate
            }// end row $i
            unset($table);
            $this->colsums = [];
        }
    }

    /* -- END TABLES -- */

    public function SetHTMLHeader($header = '', $OE = '', $write = false)
    {

        $height = 0;
        if (is_array($header) && isset($header['html']) && $header['html']) {
            $Hhtml = $header['html'];
            if ($this->setAutoTopMargin) {
                if (isset($header['h'])) {
                    $height = $header['h'];
                } else {
                    $height = $this->_getHtmlHeight($Hhtml);
                }
            }
        } elseif (!is_array($header) && $header) {
            $Hhtml = $header;
            if ($this->setAutoTopMargin) {
                $height = $this->_getHtmlHeight($Hhtml);
            }
        } else {
            $Hhtml = '';
        }

        if ($OE !== 'E') {
            $OE = 'O';
        }

        if ($OE === 'E') {
            if ($Hhtml) {
                $this->HTMLHeaderE = [];
                $this->HTMLHeaderE['html'] = $Hhtml;
                $this->HTMLHeaderE['h'] = $height;
            } else {
                $this->HTMLHeaderE = '';
            }
        } else {
            if ($Hhtml) {
                $this->HTMLHeader = [];
                $this->HTMLHeader['html'] = $Hhtml;
                $this->HTMLHeader['h'] = $height;
            } else {
                $this->HTMLHeader = '';
            }
        }

        if (!$this->mirrorMargins && $OE == 'E') {
            return;
        }
        if ($Hhtml == '') {
            return;
        }

        if ($this->setAutoTopMargin == 'pad') {
            $this->tMargin = $this->margin_header + $height + $this->orig_tMargin;
            if (isset($this->saveHTMLHeader[$this->page][$OE]['mt'])) {
                $this->saveHTMLHeader[$this->page][$OE]['mt'] = $this->tMargin;
            }
        } elseif ($this->setAutoTopMargin == 'stretch') {
            $this->tMargin = max($this->orig_tMargin, $this->margin_header + $height + $this->autoMarginPadding);
            if (isset($this->saveHTMLHeader[$this->page][$OE]['mt'])) {
                $this->saveHTMLHeader[$this->page][$OE]['mt'] = $this->tMargin;
            }
        }
        if ($write && $this->state != 0 && (($this->mirrorMargins && $OE == 'E' && ($this->page) % 2 == 0) || ($this->mirrorMargins && $OE != 'E' && ($this->page) % 2 == 1) || !$this->mirrorMargins)) {
            $this->writeHTMLHeaders();
        }
    }

    public function SetHTMLFooter($footer = '', $OE = '')
    {
        $height = 0;
        if (is_array($footer) && isset($footer['html']) && $footer['html']) {
            $Fhtml = $footer['html'];
            if ($this->setAutoBottomMargin) {
                if (isset($footer['h'])) {
                    $height = $footer['h'];
                } else {
                    $height = $this->_getHtmlHeight($Fhtml);
                }
            }
        } elseif (!is_array($footer) && $footer) {
            $Fhtml = $footer;
            if ($this->setAutoBottomMargin) {
                $height = $this->_getHtmlHeight($Fhtml);
            }
        } else {
            $Fhtml = '';
        }

        if ($OE !== 'E') {
            $OE = 'O';
        }

        if ($OE === 'E') {
            if ($Fhtml) {
                $this->HTMLFooterE = [];
                $this->HTMLFooterE['html'] = $Fhtml;
                $this->HTMLFooterE['h'] = $height;
            } else {
                $this->HTMLFooterE = '';
            }
        } else {
            if ($Fhtml) {
                $this->HTMLFooter = [];
                $this->HTMLFooter['html'] = $Fhtml;
                $this->HTMLFooter['h'] = $height;
            } else {
                $this->HTMLFooter = '';
            }
        }

        if (!$this->mirrorMargins && $OE == 'E') {
            return;
        }

        if ($Fhtml == '') {
            return false;
        }

        if ($this->setAutoBottomMargin == 'pad') {
            $this->bMargin = $this->margin_footer + $height + $this->orig_bMargin;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            if (isset($this->saveHTMLHeader[$this->page][$OE]['mb'])) {
                $this->saveHTMLHeader[$this->page][$OE]['mb'] = $this->bMargin;
            }
        } elseif ($this->setAutoBottomMargin == 'stretch') {
            $this->bMargin = max($this->orig_bMargin, $this->margin_footer + $height + $this->autoMarginPadding);
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            if (isset($this->saveHTMLHeader[$this->page][$OE]['mb'])) {
                $this->saveHTMLHeader[$this->page][$OE]['mb'] = $this->bMargin;
            }
        }
    }

    // Called internally from Header
    public function writeHTMLHeaders()
    {

        if ($this->mirrorMargins && ($this->page) % 2 == 0) {
            $OE = 'E';
        } else {
            $OE = 'O';
        }

        if ($OE === 'E') {
            $this->saveHTMLHeader[$this->page][$OE]['html'] = $this->HTMLHeaderE['html'];
        } else {
            $this->saveHTMLHeader[$this->page][$OE]['html'] = $this->HTMLHeader['html'];
        }

        if ($this->forcePortraitHeaders && $this->CurOrientation == 'L' && $this->CurOrientation != $this->DefOrientation) {
            $this->saveHTMLHeader[$this->page][$OE]['rotate'] = true;
            $this->saveHTMLHeader[$this->page][$OE]['ml'] = $this->tMargin;
            $this->saveHTMLHeader[$this->page][$OE]['mr'] = $this->bMargin;
            $this->saveHTMLHeader[$this->page][$OE]['mh'] = $this->margin_header;
            $this->saveHTMLHeader[$this->page][$OE]['mf'] = $this->margin_footer;
            $this->saveHTMLHeader[$this->page][$OE]['pw'] = $this->h;
            $this->saveHTMLHeader[$this->page][$OE]['ph'] = $this->w;
        } else {
            $this->saveHTMLHeader[$this->page][$OE]['ml'] = $this->lMargin;
            $this->saveHTMLHeader[$this->page][$OE]['mr'] = $this->rMargin;
            $this->saveHTMLHeader[$this->page][$OE]['mh'] = $this->margin_header;
            $this->saveHTMLHeader[$this->page][$OE]['mf'] = $this->margin_footer;
            $this->saveHTMLHeader[$this->page][$OE]['pw'] = $this->w;
            $this->saveHTMLHeader[$this->page][$OE]['ph'] = $this->h;
        }
    }

    public function writeHTMLFooters()
    {

        if ($this->mirrorMargins && ($this->page) % 2 == 0) {
            $OE = 'E';
        } else {
            $OE = 'O';
        }

        if ($OE === 'E') {
            $this->saveHTMLFooter[$this->page][$OE]['html'] = $this->HTMLFooterE['html'];
        } else {
            $this->saveHTMLFooter[$this->page][$OE]['html'] = $this->HTMLFooter['html'];
        }

        if ($this->forcePortraitHeaders && $this->CurOrientation == 'L' && $this->CurOrientation != $this->DefOrientation) {
            $this->saveHTMLFooter[$this->page][$OE]['rotate'] = true;
            $this->saveHTMLFooter[$this->page][$OE]['ml'] = $this->tMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mr'] = $this->bMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mt'] = $this->rMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mb'] = $this->lMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mh'] = $this->margin_header;
            $this->saveHTMLFooter[$this->page][$OE]['mf'] = $this->margin_footer;
            $this->saveHTMLFooter[$this->page][$OE]['pw'] = $this->h;
            $this->saveHTMLFooter[$this->page][$OE]['ph'] = $this->w;
        } else {
            $this->saveHTMLFooter[$this->page][$OE]['ml'] = $this->lMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mr'] = $this->rMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mt'] = $this->tMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mb'] = $this->bMargin;
            $this->saveHTMLFooter[$this->page][$OE]['mh'] = $this->margin_header;
            $this->saveHTMLFooter[$this->page][$OE]['mf'] = $this->margin_footer;
            $this->saveHTMLFooter[$this->page][$OE]['pw'] = $this->w;
            $this->saveHTMLFooter[$this->page][$OE]['ph'] = $this->h;
        }
    }

    public function DefHeaderByName($name, $arr)
    {
        if (!$name) {
            $name = '_nonhtmldefault';
        }
        $html = $this->_createHTMLheaderFooter($arr, 'H');

        $this->pageHTMLheaders[$name]['html'] = $html;
        $this->pageHTMLheaders[$name]['h'] = $this->_getHtmlHeight($html);
    }

    public function DefFooterByName($name, $arr)
    {
        if (!$name) {
            $name = '_nonhtmldefault';
        }
        $html = $this->_createHTMLheaderFooter($arr, 'F');

        $this->pageHTMLfooters[$name]['html'] = $html;
        $this->pageHTMLfooters[$name]['h'] = $this->_getHtmlHeight($html);
    }

    public function SetHeaderByName($name, $side = 'O', $write = false)
    {
        if (!$name) {
            $name = '_nonhtmldefault';
        }
        $this->SetHTMLHeader($this->pageHTMLheaders[$name], $side, $write);
    }

    public function SetFooterByName($name, $side = 'O')
    {
        if (!$name) {
            $name = '_nonhtmldefault';
        }
        $this->SetHTMLFooter($this->pageHTMLfooters[$name], $side);
    }

    public function DefHTMLHeaderByName($name, $html)
    {
        if (!$name) {
            $name = '_default';
        }

        $this->pageHTMLheaders[$name]['html'] = $html;
        $this->pageHTMLheaders[$name]['h'] = $this->_getHtmlHeight($html);
    }

    public function DefHTMLFooterByName($name, $html)
    {
        if (!$name) {
            $name = '_default';
        }

        $this->pageHTMLfooters[$name]['html'] = $html;
        $this->pageHTMLfooters[$name]['h'] = $this->_getHtmlHeight($html);
    }

    public function SetHTMLHeaderByName($name, $side = 'O', $write = false)
    {
        if (!$name) {
            $name = '_default';
        }
        $this->SetHTMLHeader($this->pageHTMLheaders[$name], $side, $write);
    }

    public function SetHTMLFooterByName($name, $side = 'O')
    {
        if (!$name) {
            $name = '_default';
        }
        $this->SetHTMLFooter($this->pageHTMLfooters[$name], $side);
    }

    public function SetHeader($Harray = [], $side = '', $write = false)
    {
        $oddhtml = '';
        $evenhtml = '';
        if (is_string($Harray)) {
            if (strlen($Harray) == 0) {
                $oddhtml = '';
                $evenhtml = '';
            } elseif (strpos($Harray, '|') !== false) {
                $hdet = explode('|', $Harray);
                list($lw, $cw, $rw) = $this->_shareHeaderFooterWidth($hdet[0], $hdet[1], $hdet[2]);
                $oddhtml = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: bottom; color: #000000; ';
                if ($this->defaultheaderfontsize) {
                    $oddhtml .= ' font-size: ' . $this->defaultheaderfontsize . 'pt;';
                }
                if ($this->defaultheaderfontstyle) {
                    if ($this->defaultheaderfontstyle == 'B' || $this->defaultheaderfontstyle == 'BI') {
                        $oddhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultheaderfontstyle == 'I' || $this->defaultheaderfontstyle == 'BI') {
                        $oddhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultheaderline) {
                    $oddhtml .= ' border-bottom: 0.1mm solid #000000;';
                }
                $oddhtml .= '">';
                $oddhtml .= '<tr>';
                $oddhtml .= '<td width="' . $lw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: left; ">' . $hdet[0] . '</td>';
                $oddhtml .= '<td width="' . $cw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: center; ">' . $hdet[1] . '</td>';
                $oddhtml .= '<td width="' . $rw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: right; ">' . $hdet[2] . '</td>';
                $oddhtml .= '</tr></table>';

                $evenhtml = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: bottom; color: #000000; ';
                if ($this->defaultheaderfontsize) {
                    $evenhtml .= ' font-size: ' . $this->defaultheaderfontsize . 'pt;';
                }
                if ($this->defaultheaderfontstyle) {
                    if ($this->defaultheaderfontstyle == 'B' || $this->defaultheaderfontstyle == 'BI') {
                        $evenhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultheaderfontstyle == 'I' || $this->defaultheaderfontstyle == 'BI') {
                        $evenhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultheaderline) {
                    $evenhtml .= ' border-bottom: 0.1mm solid #000000;';
                }
                $evenhtml .= '">';
                $evenhtml .= '<tr>';
                $evenhtml .= '<td width="' . $rw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: left; ">' . $hdet[2] . '</td>';
                $evenhtml .= '<td width="' . $cw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: center; ">' . $hdet[1] . '</td>';
                $evenhtml .= '<td width="' . $lw . '%" style="padding: 0 0 ' . $this->header_line_spacing . 'em 0; text-align: right; ">' . $hdet[0] . '</td>';
                $evenhtml .= '</tr></table>';
            } else {
                $oddhtml = '<div style="margin: 0; color: #000000; ';
                if ($this->defaultheaderfontsize) {
                    $oddhtml .= ' font-size: ' . $this->defaultheaderfontsize . 'pt;';
                }
                if ($this->defaultheaderfontstyle) {
                    if ($this->defaultheaderfontstyle == 'B' || $this->defaultheaderfontstyle == 'BI') {
                        $oddhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultheaderfontstyle == 'I' || $this->defaultheaderfontstyle == 'BI') {
                        $oddhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultheaderline) {
                    $oddhtml .= ' border-bottom: 0.1mm solid #000000;';
                }
                $oddhtml .= 'text-align: right; ">' . $Harray . '</div>';

                $evenhtml = '<div style="margin: 0; color: #000000; ';
                if ($this->defaultheaderfontsize) {
                    $evenhtml .= ' font-size: ' . $this->defaultheaderfontsize . 'pt;';
                }
                if ($this->defaultheaderfontstyle) {
                    if ($this->defaultheaderfontstyle == 'B' || $this->defaultheaderfontstyle == 'BI') {
                        $evenhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultheaderfontstyle == 'I' || $this->defaultheaderfontstyle == 'BI') {
                        $evenhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultheaderline) {
                    $evenhtml .= ' border-bottom: 0.1mm solid #000000;';
                }
                $evenhtml .= 'text-align: left; ">' . $Harray . '</div>';
            }
        } elseif (is_array($Harray) && !empty($Harray)) {
            if ($side == 'O') {
                $odd = $Harray;
            } elseif ($side == 'E') {
                $even = $Harray;
            } else {
                $odd = $Harray['odd'];
                $even = $Harray['even'];
            }
            $oddhtml = $this->_createHTMLheaderFooter($odd, 'H');

            $evenhtml = $this->_createHTMLheaderFooter($even, 'H');
        }

        if ($side == 'E') {
            $this->SetHTMLHeader($evenhtml, 'E', $write);
        } elseif ($side == 'O') {
            $this->SetHTMLHeader($oddhtml, 'O', $write);
        } else {
            $this->SetHTMLHeader($oddhtml, 'O', $write);
            $this->SetHTMLHeader($evenhtml, 'E', $write);
        }
    }

    public function SetFooter($Farray = [], $side = '')
    {
        $oddhtml = '';
        $evenhtml = '';
        if (is_string($Farray)) {
            if (strlen($Farray) == 0) {
                $oddhtml = '';
                $evenhtml = '';
            } elseif (strpos($Farray, '|') !== false) {
                $hdet = explode('|', $Farray);
                $oddhtml = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: top; color: #000000; ';
                if ($this->defaultfooterfontsize) {
                    $oddhtml .= ' font-size: ' . $this->defaultfooterfontsize . 'pt;';
                }
                if ($this->defaultfooterfontstyle) {
                    if ($this->defaultfooterfontstyle == 'B' || $this->defaultfooterfontstyle == 'BI') {
                        $oddhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultfooterfontstyle == 'I' || $this->defaultfooterfontstyle == 'BI') {
                        $oddhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultfooterline) {
                    $oddhtml .= ' border-top: 0.1mm solid #000000;';
                }
                $oddhtml .= '">';
                $oddhtml .= '<tr>';
                $oddhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: left; ">' . $hdet[0] . '</td>';
                $oddhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: center; ">' . $hdet[1] . '</td>';
                $oddhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: right; ">' . $hdet[2] . '</td>';
                $oddhtml .= '</tr></table>';

                $evenhtml = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: top; color: #000000; ';
                if ($this->defaultfooterfontsize) {
                    $evenhtml .= ' font-size: ' . $this->defaultfooterfontsize . 'pt;';
                }
                if ($this->defaultfooterfontstyle) {
                    if ($this->defaultfooterfontstyle == 'B' || $this->defaultfooterfontstyle == 'BI') {
                        $evenhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultfooterfontstyle == 'I' || $this->defaultfooterfontstyle == 'BI') {
                        $evenhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultfooterline) {
                    $evenhtml .= ' border-top: 0.1mm solid #000000;';
                }
                $evenhtml .= '">';
                $evenhtml .= '<tr>';
                $evenhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: left; ">' . $hdet[2] . '</td>';
                $evenhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: center; ">' . $hdet[1] . '</td>';
                $evenhtml .= '<td width="33%" style="padding: ' . $this->footer_line_spacing . 'em 0 0 0; text-align: right; ">' . $hdet[0] . '</td>';
                $evenhtml .= '</tr></table>';
            } else {
                $oddhtml = '<div style="margin: 0; color: #000000; ';
                if ($this->defaultfooterfontsize) {
                    $oddhtml .= ' font-size: ' . $this->defaultfooterfontsize . 'pt;';
                }
                if ($this->defaultfooterfontstyle) {
                    if ($this->defaultfooterfontstyle == 'B' || $this->defaultfooterfontstyle == 'BI') {
                        $oddhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultfooterfontstyle == 'I' || $this->defaultfooterfontstyle == 'BI') {
                        $oddhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultfooterline) {
                    $oddhtml .= ' border-top: 0.1mm solid #000000;';
                }
                $oddhtml .= 'text-align: right; ">' . $Farray . '</div>';

                $evenhtml = '<div style="margin: 0; color: #000000; ';
                if ($this->defaultfooterfontsize) {
                    $evenhtml .= ' font-size: ' . $this->defaultfooterfontsize . 'pt;';
                }
                if ($this->defaultfooterfontstyle) {
                    if ($this->defaultfooterfontstyle == 'B' || $this->defaultfooterfontstyle == 'BI') {
                        $evenhtml .= ' font-weight: bold;';
                    }
                    if ($this->defaultfooterfontstyle == 'I' || $this->defaultfooterfontstyle == 'BI') {
                        $evenhtml .= ' font-style: italic;';
                    }
                }
                if ($this->defaultfooterline) {
                    $evenhtml .= ' border-top: 0.1mm solid #000000;';
                }
                $evenhtml .= 'text-align: left; ">' . $Farray . '</div>';
            }
        } elseif (is_array($Farray)) {
            if ($side == 'O') {
                $odd = $Farray;
            } elseif ($side == 'E') {
                $even = $Farray;
            } else {
                if (isset($Farray['odd'])) {
                    $odd = $Farray['odd'];
                }
                if (isset($Farray['even'])) {
                    $even = $Farray['even'];
                }
            }

            if (isset($odd)) {
                $oddhtml = $this->_createHTMLheaderFooter($odd, 'F');
            }

            if (isset($even)) {
                $evenhtml = $this->_createHTMLheaderFooter($even, 'F');
            }
        }
        /* -- HTMLfooterS-FOOTERS -- */
        if ($side == 'E') {
            $this->SetHTMLFooter($evenhtml, 'E');
        } elseif ($side == 'O') {
            $this->SetHTMLFooter($oddhtml, 'O');
        } else {
            $this->SetHTMLFooter($oddhtml, 'O');
            $this->SetHTMLFooter($evenhtml, 'E');
        }
        /* -- END HTMLfooterS-FOOTERS -- */
    }

    /* -- WATERMARK -- */

    public function SetWatermarkText($txt = '', $alpha = -1)
    {
        if ($alpha >= 0) {
            $this->watermarkTextAlpha = $alpha;
        }
        $this->watermarkText = $txt;
    }

    public function SetWatermarkImage($src, $alpha = -1, $size = 'D', $pos = 'F')
    {
        if ($alpha >= 0) {
            $this->watermarkImageAlpha = $alpha;
        }
        $this->watermarkImage = $src;
        $this->watermark_size = $size;
        $this->watermark_pos = $pos;
    }

    /* -- END WATERMARK -- */

    // Page footer
    public function Footer()
    {
        /* -- CSS-PAGE -- */
        // PAGED MEDIA - CROP / CROSS MARKS from @PAGE
        if ($this->show_marks == 'CROP' || $this->show_marks == 'CROPCROSS') {
            // Show TICK MARKS
            $this->SetLineWidth(0.1); // = 0.1 mm
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $l = $this->cropMarkLength;
            $m = $this->cropMarkMargin; // Distance of crop mark from margin
            $b = $this->nonPrintMargin; // Non-printable border at edge of paper sheet
            $ax1 = $b;
            $bx = $this->page_box['outer_width_LR'] - $m;
            $ax = max($ax1, $bx - $l);
            $cx1 = $this->w - $b;
            $dx = $this->w - $this->page_box['outer_width_LR'] + $m;
            $cx = min($cx1, $dx + $l);
            $ay1 = $b;
            $by = $this->page_box['outer_width_TB'] - $m;
            $ay = max($ay1, $by - $l);
            $cy1 = $this->h - $b;
            $dy = $this->h - $this->page_box['outer_width_TB'] + $m;
            $cy = min($cy1, $dy + $l);

            $this->Line($ax, $this->page_box['outer_width_TB'], $bx, $this->page_box['outer_width_TB']);
            $this->Line($cx, $this->page_box['outer_width_TB'], $dx, $this->page_box['outer_width_TB']);
            $this->Line($ax, $this->h - $this->page_box['outer_width_TB'], $bx, $this->h - $this->page_box['outer_width_TB']);
            $this->Line($cx, $this->h - $this->page_box['outer_width_TB'], $dx, $this->h - $this->page_box['outer_width_TB']);
            $this->Line($this->page_box['outer_width_LR'], $ay, $this->page_box['outer_width_LR'], $by);
            $this->Line($this->page_box['outer_width_LR'], $cy, $this->page_box['outer_width_LR'], $dy);
            $this->Line($this->w - $this->page_box['outer_width_LR'], $ay, $this->w - $this->page_box['outer_width_LR'], $by);
            $this->Line($this->w - $this->page_box['outer_width_LR'], $cy, $this->w - $this->page_box['outer_width_LR'], $dy);

            if ($this->printers_info) {
                $hd = date('Y-m-d H:i') . '  Page ' . $this->page . ' of {nb}';
                $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                $this->SetFont('arial', '', 7.5, true, true);
                $this->x = $this->page_box['outer_width_LR'] + 1.5;
                $this->y = 1;
                $this->Cell($headerpgwidth, $this->FontSize, $hd, 0, 0, 'L', 0, '', 0, 0, 0, 'M');
                $this->SetFont($this->default_font, '', $this->original_default_font_size);
            }
        }
        if ($this->show_marks == 'CROSS' || $this->show_marks == 'CROPCROSS') {
            $this->SetLineWidth(0.1); // = 0.1 mm
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $l = 14 / 2; // longer length of the cross line (half)
            $w = 6 / 2; // shorter width of the cross line (half)
            $r = 1.2; // radius of circle
            $m = $this->crossMarkMargin; // Distance of cross mark from margin
            $x1 = $this->page_box['outer_width_LR'] - $m;
            $x2 = $this->w - $this->page_box['outer_width_LR'] + $m;
            $y1 = $this->page_box['outer_width_TB'] - $m;
            $y2 = $this->h - $this->page_box['outer_width_TB'] + $m;
            // Left
            $this->Circle($x1, $this->h / 2, $r, 'S');
            $this->Line($x1 - $w, $this->h / 2, $x1 + $w, $this->h / 2);
            $this->Line($x1, $this->h / 2 - $l, $x1, $this->h / 2 + $l);
            // Right
            $this->Circle($x2, $this->h / 2, $r, 'S');
            $this->Line($x2 - $w, $this->h / 2, $x2 + $w, $this->h / 2);
            $this->Line($x2, $this->h / 2 - $l, $x2, $this->h / 2 + $l);
            // Top
            $this->Circle($this->w / 2, $y1, $r, 'S');
            $this->Line($this->w / 2, $y1 - $w, $this->w / 2, $y1 + $w);
            $this->Line($this->w / 2 - $l, $y1, $this->w / 2 + $l, $y1);
            // Bottom
            $this->Circle($this->w / 2, $y2, $r, 'S');
            $this->Line($this->w / 2, $y2 - $w, $this->w / 2, $y2 + $w);
            $this->Line($this->w / 2 - $l, $y2, $this->w / 2 + $l, $y2);
        }

        /* -- END CSS-PAGE -- */

        // mPDF 6
        // If @page set non-HTML headers/footers named, they were not read until later in the HTML code - so now set them
        if ($this->page == 1) {
            if ($this->firstPageBoxHeader) {
                if (isset($this->pageHTMLheaders[$this->firstPageBoxHeader])) {
                    $this->HTMLHeader = $this->pageHTMLheaders[$this->firstPageBoxHeader];
                }
                $this->Header();
            }
            if ($this->firstPageBoxFooter) {
                if (isset($this->pageHTMLfooters[$this->firstPageBoxFooter])) {
                    $this->HTMLFooter = $this->pageHTMLfooters[$this->firstPageBoxFooter];
                }
            }
            $this->firstPageBoxHeader = '';
            $this->firstPageBoxFooter = '';
        }


        if (($this->mirrorMargins && ($this->page % 2 == 0) && $this->HTMLFooterE) || ($this->mirrorMargins && ($this->page % 2 == 1) && $this->HTMLFooter) || (!$this->mirrorMargins && $this->HTMLFooter)) {
            $this->writeHTMLFooters();
        }

        /* -- WATERMARK -- */
        if (($this->watermarkText) && ($this->showWatermarkText)) {
            $this->watermark($this->watermarkText, $this->watermarkAngle, 120, $this->watermarkTextAlpha); // Watermark text
        }
        if (($this->watermarkImage) && ($this->showWatermarkImage)) {
            $this->watermarkImg($this->watermarkImage, $this->watermarkImageAlpha); // Watermark image
        }
        /* -- END WATERMARK -- */
    }

    /* -- HTML-CSS -- */

    /**
     * HTML parser
     *
     * @param string $html
     * @param int $sub 0 = default;
     *                 1 = headerCSS only
     *                 2 = HTML body (parts) only;
     *                 3 = HTML parses only
     *                 4 = writes HTML headers/Fixed pos DIVs - stores in buffer - for single page only
     * @param bool $init Clears and sets buffers to Top level block etc.
     * @param bool $close If false leaves buffers etc. in current state, so that it can continue a block etc.
     */
    public function WriteHTML($html, $sub = 0, $init = true, $close = true)
    {
        /* Check $html is an integer, float, string, boolean or class with __toString(), otherwise throw exception */
        if (is_scalar($html) === false) {
            if (!is_object($html) || ! method_exists($html, '__toString')) {
                throw new \Mpdf\MpdfException('WriteHTML() requires $html be an integer, float, string, boolean or an object with the __toString() magic method.');
            }
        }

        /* Cast $html as a string */
        $html = (string) $html;

        // @log Parsing CSS & Headers

        if ($init) {
            $this->headerbuffer = '';
            $this->textbuffer = [];
            $this->fixedPosBlockSave = [];
        }
        if ($sub == 1) {
            $html = '<style> ' . $html . ' </style>';
        } // stylesheet only

        if ($this->allow_charset_conversion) {
            if ($sub < 1) {
                $this->ReadCharset($html);
            }
            if ($this->charset_in && $sub != 4) {
                $success = iconv($this->charset_in, 'UTF-8//TRANSLIT', $html);
                if ($success) {
                    $html = $success;
                }
            }
        }

        $html = $this->purify_utf8($html, false);
        if ($init) {
            $this->blklvl = 0;
            $this->lastblocklevelchange = 0;
            $this->blk = [];
            $this->initialiseBlock($this->blk[0]);
            $this->blk[0]['width'] = & $this->pgwidth;
            $this->blk[0]['inner_width'] = & $this->pgwidth;
            $this->blk[0]['blockContext'] = $this->blockContext;
        }

        $zproperties = [];
        if ($sub < 2) {
            $this->ReadMetaTags($html);

            if (preg_match('/<base[^>]*href=["\']([^"\'>]*)["\']/i', $html, $m)) {
                $this->SetBasePath($m[1]);
            }
            $html = $this->cssManager->ReadCSS($html);

            if ($this->autoLangToFont && !$this->usingCoreFont && preg_match('/<html [^>]*lang=[\'\"](.*?)[\'\"]/ism', $html, $m)) {
                $html_lang = $m[1];
            }

            if (preg_match('/<html [^>]*dir=[\'\"]\s*rtl\s*[\'\"]/ism', $html)) {
                $zproperties['DIRECTION'] = 'rtl';
            }

            // allow in-line CSS for body tag to be parsed // Get <body> tag inline CSS
            if (preg_match('/<body([^>]*)>(.*?)<\/body>/ism', $html, $m) || preg_match('/<body([^>]*)>(.*)$/ism', $html, $m)) {
                $html = $m[2];
                // Changed to allow style="background: url('bg.jpg')"
                if (preg_match('/style=[\"](.*?)[\"]/ism', $m[1], $mm) || preg_match('/style=[\'](.*?)[\']/ism', $m[1], $mm)) {
                    $zproperties = $this->cssManager->readInlineCSS($mm[1]);
                }
                if (preg_match('/dir=[\'\"]\s*rtl\s*[\'\"]/ism', $m[1])) {
                    $zproperties['DIRECTION'] = 'rtl';
                }
                if (isset($html_lang) && $html_lang) {
                    $zproperties['LANG'] = $html_lang;
                }
                if ($this->autoLangToFont && !$this->onlyCoreFonts && preg_match('/lang=[\'\"](.*?)[\'\"]/ism', $m[1], $mm)) {
                    $zproperties['LANG'] = $mm[1];
                }
            }
        }
        $properties = $this->cssManager->MergeCSS('BLOCK', 'BODY', '');
        if ($zproperties) {
            $properties = $this->cssManager->array_merge_recursive_unique($properties, $zproperties);
        }

        if (isset($properties['DIRECTION']) && $properties['DIRECTION']) {
            $this->cssManager->CSS['BODY']['DIRECTION'] = $properties['DIRECTION'];
        }
        if (!isset($this->cssManager->CSS['BODY']['DIRECTION'])) {
            $this->cssManager->CSS['BODY']['DIRECTION'] = $this->directionality;
        } else {
            $this->SetDirectionality($this->cssManager->CSS['BODY']['DIRECTION']);
        }

        $this->setCSS($properties, '', 'BODY');

        $this->blk[0]['InlineProperties'] = $this->saveInlineProperties();

        if ($sub == 1) {
            return '';
        }
        if (!isset($this->cssManager->CSS['BODY'])) {
            $this->cssManager->CSS['BODY'] = [];
        }

        /* -- BACKGROUNDS -- */
        if (isset($properties['BACKGROUND-GRADIENT'])) {
            $this->bodyBackgroundGradient = $properties['BACKGROUND-GRADIENT'];
        }

        if (isset($properties['BACKGROUND-IMAGE']) && $properties['BACKGROUND-IMAGE']) {
            $ret = $this->SetBackground($properties, $this->pgwidth);
            if ($ret) {
                $this->bodyBackgroundImage = $ret;
            }
        }
        /* -- END BACKGROUNDS -- */

        /* -- CSS-PAGE -- */
        // If page-box is set
        if ($this->state == 0 && ((isset($this->cssManager->CSS['@PAGE']) && $this->cssManager->CSS['@PAGE']) || (isset($this->cssManager->CSS['@PAGE>>PSEUDO>>FIRST']) && $this->cssManager->CSS['@PAGE>>PSEUDO>>FIRST']))) { // mPDF 5.7.3
            $this->page_box['current'] = '';
            $this->page_box['using'] = true;
            list($pborientation, $pbmgl, $pbmgr, $pbmgt, $pbmgb, $pbmgh, $pbmgf, $hname, $fname, $bg, $resetpagenum, $pagenumstyle, $suppress, $marks, $newformat) = $this->SetPagedMediaCSS('', false, 'O');
            $this->DefOrientation = $this->CurOrientation = $pborientation;
            $this->orig_lMargin = $this->DeflMargin = $pbmgl;
            $this->orig_rMargin = $this->DefrMargin = $pbmgr;
            $this->orig_tMargin = $this->tMargin = $pbmgt;
            $this->orig_bMargin = $this->bMargin = $pbmgb;
            $this->orig_hMargin = $this->margin_header = $pbmgh;
            $this->orig_fMargin = $this->margin_footer = $pbmgf;
            list($pborientation, $pbmgl, $pbmgr, $pbmgt, $pbmgb, $pbmgh, $pbmgf, $hname, $fname, $bg, $resetpagenum, $pagenumstyle, $suppress, $marks, $newformat) = $this->SetPagedMediaCSS('', true, 'O'); // first page
            $this->show_marks = $marks;
            if ($hname) {
                $this->firstPageBoxHeader = $hname;
            }
            if ($fname) {
                $this->firstPageBoxFooter = $fname;
            }
        }
        /* -- END CSS-PAGE -- */

        $parseonly = false;
        $this->bufferoutput = false;
        if ($sub == 3) {
            $parseonly = true;
            // Close any open block tags
            $arr = [];
            $ai = 0;
            for ($b = $this->blklvl; $b > 0; $b--) {
                $this->tag->CloseTag($this->blk[$b]['tag'], $arr, $ai);
            }
            // Output any text left in buffer
            if (count($this->textbuffer)) {
                $this->printbuffer($this->textbuffer);
            }
            $this->textbuffer = [];
        } elseif ($sub == 4) {
            // Close any open block tags
            $arr = [];
            $ai = 0;
            for ($b = $this->blklvl; $b > 0; $b--) {
                $this->tag->CloseTag($this->blk[$b]['tag'], $arr, $ai);
            }
            // Output any text left in buffer
            if (count($this->textbuffer)) {
                $this->printbuffer($this->textbuffer);
            }
            $this->bufferoutput = true;
            $this->textbuffer = [];
            $this->headerbuffer = '';
            $properties = $this->cssManager->MergeCSS('BLOCK', 'BODY', '');
            $this->setCSS($properties, '', 'BODY');
        }

        mb_internal_encoding('UTF-8');

        $html = $this->AdjustHTML($html, $this->tabSpaces); // Try to make HTML look more like XHTML

        if ($this->autoScriptToLang) {
            $html = $this->markScriptToLang($html);
        }

        preg_match_all('/<htmlpageheader([^>]*)>(.*?)<\/htmlpageheader>/si', $html, $h);
        for ($i = 0; $i < count($h[1]); $i++) {
            if (preg_match('/name=[\'|\"](.*?)[\'|\"]/', $h[1][$i], $n)) {
                $this->pageHTMLheaders[$n[1]]['html'] = $h[2][$i];
                $this->pageHTMLheaders[$n[1]]['h'] = $this->_getHtmlHeight($h[2][$i]);
            }
        }
        preg_match_all('/<htmlpagefooter([^>]*)>(.*?)<\/htmlpagefooter>/si', $html, $f);
        for ($i = 0; $i < count($f[1]); $i++) {
            if (preg_match('/name=[\'|\"](.*?)[\'|\"]/', $f[1][$i], $n)) {
                $this->pageHTMLfooters[$n[1]]['html'] = $f[2][$i];
                $this->pageHTMLfooters[$n[1]]['h'] = $this->_getHtmlHeight($f[2][$i]);
            }
        }

        $html = preg_replace('/<htmlpageheader.*?<\/htmlpageheader>/si', '', $html);
        $html = preg_replace('/<htmlpagefooter.*?<\/htmlpagefooter>/si', '', $html);

        if ($this->state == 0 && $sub != 1 && $sub != 3 && $sub != 4) {
            $this->AddPage($this->CurOrientation);
        }


        if (isset($hname) && preg_match('/^html_(.*)$/i', $hname, $n)) {
            $this->SetHTMLHeader($this->pageHTMLheaders[$n[1]], 'O', true);
        }
        if (isset($fname) && preg_match('/^html_(.*)$/i', $fname, $n)) {
            $this->SetHTMLFooter($this->pageHTMLfooters[$n[1]], 'O');
        }



        $html = str_replace('<?', '< ', $html); // Fix '<?XML' bug from HTML code generated by MS Word

        $this->checkSIP = false;
        $this->checkSMP = false;
        $this->checkCJK = false;
        if ($this->onlyCoreFonts) {
            $html = $this->SubstituteChars($html);
        } else {
            if (preg_match("/([" . $this->pregRTLchars . "])/u", $html)) {
                $this->biDirectional = true;
            } // *OTL*
            if (preg_match("/([\x{20000}-\x{2FFFF}])/u", $html)) {
                $this->checkSIP = true;
            }
            if (preg_match("/([\x{10000}-\x{1FFFF}])/u", $html)) {
                $this->checkSMP = true;
            }
            /* -- CJK-FONTS -- */
            if (preg_match("/([" . $this->pregCJKchars . "])/u", $html)) {
                $this->checkCJK = true;
            }
            /* -- END CJK-FONTS -- */
        }

        // Don't allow non-breaking spaces that are converted to substituted chars or will break anyway and mess up table width calc.
        $html = str_replace('<tta>160</tta>', chr(32), $html);
        $html = str_replace('</tta><tta>', '|', $html);
        $html = str_replace('</tts><tts>', '|', $html);
        $html = str_replace('</ttz><ttz>', '|', $html);

        // Add new supported tags in the DisableTags function
        $html = strip_tags($html, $this->enabledtags); // remove all unsupported tags, but the ones inside the 'enabledtags' string
        // Explode the string in order to parse the HTML code
        $a = preg_split('/<(.*?)>/ms', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        // ? more accurate regexp that allows e.g. <a name="Silly <name>">
        // if changing - also change in fn.SubstituteChars()
        // $a = preg_split ('/<((?:[^<>]+(?:"[^"]*"|\'[^\']*\')?)+)>/ms', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($this->mb_enc) {
            mb_internal_encoding($this->mb_enc);
        }
        $pbc = 0;
        $this->subPos = -1;
        $cnt = count($a);
        for ($i = 0; $i < $cnt; $i++) {
            $e = $a[$i];
            if ($i % 2 == 0) {
                // TEXT
                if ($this->blk[$this->blklvl]['hide']) {
                    continue;
                }
                if ($this->inlineDisplayOff) {
                    continue;
                }
                if ($this->inMeter) {
                    continue;
                }

                if ($this->inFixedPosBlock) {
                    $this->fixedPosBlock .= $e;
                    continue;
                } // *CSS-POSITION*
                if (strlen($e) == 0) {
                    continue;
                }

                if ($this->ignorefollowingspaces && !$this->ispre) {
                    if (strlen(ltrim($e)) == 0) {
                        continue;
                    }
                    if ($this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats' && substr($e, 0, 1) == ' ') {
                        $this->ignorefollowingspaces = false;
                        $e = ltrim($e);
                    }
                }

                $this->OTLdata = null;  // mPDF 5.7.1

                $e = UtfString::strcode2utf($e);
                $e = $this->lesser_entity_decode($e);

                if ($this->usingCoreFont) {
                    // If core font is selected in document which is not onlyCoreFonts - substitute with non-core font
                    if ($this->useSubstitutions && !$this->onlyCoreFonts && $this->subPos < $i && !$this->specialcontent) {
                        $cnt += $this->SubstituteCharsNonCore($a, $i, $e);
                    }
                    // CONVERT ENCODING
                    $e = mb_convert_encoding($e, $this->mb_enc, 'UTF-8');
                    if ($this->textvar & TextVars::FT_UPPERCASE) {
                        $e = mb_strtoupper($e, $this->mb_enc);
                    } // mPDF 5.7.1
                    elseif ($this->textvar & TextVars::FT_LOWERCASE) {
                        $e = mb_strtolower($e, $this->mb_enc);
                    } // mPDF 5.7.1
                    elseif ($this->textvar & TextVars::FT_CAPITALIZE) {
                        $e = mb_convert_case($e, MB_CASE_TITLE, "UTF-8");
                    } // mPDF 5.7.1
                } else {
                    if ($this->checkSIP && $this->CurrentFont['sipext'] && $this->subPos < $i && (!$this->specialcontent || !$this->useActiveForms)) {
                        $cnt += $this->SubstituteCharsSIP($a, $i, $e);
                    }

                    if ($this->useSubstitutions && !$this->onlyCoreFonts && $this->CurrentFont['type'] != 'Type0' && $this->subPos < $i && (!$this->specialcontent || !$this->useActiveForms)) {
                        $cnt += $this->SubstituteCharsMB($a, $i, $e);
                    }

                    if ($this->textvar & TextVars::FT_UPPERCASE) {
                        $e = mb_strtoupper($e, $this->mb_enc);
                    } elseif ($this->textvar & TextVars::FT_LOWERCASE) {
                        $e = mb_strtolower($e, $this->mb_enc);
                    } elseif ($this->textvar & TextVars::FT_CAPITALIZE) {
                        $e = mb_convert_case($e, MB_CASE_TITLE, "UTF-8");
                    }

                    /* -- OTL -- */
                    // Use OTL OpenType Table Layout - GSUB & GPOS
                    if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL'] && (!$this->specialcontent || !$this->useActiveForms)) {
                        if (!$this->otl) {
                            $this->otl = new Otl($this, $this->fontCache);
                        }
                        $e = $this->otl->applyOTL($e, $this->CurrentFont['useOTL']);
                        $this->OTLdata = $this->otl->OTLdata;
                        $this->otl->removeChar($e, $this->OTLdata, "\xef\xbb\xbf"); // Remove ZWNBSP (also Byte order mark FEFF)
                    } /* -- END OTL -- */
                    else {
                        // removes U+200E/U+200F LTR and RTL mark and U+200C/U+200D Zero-width Joiner and Non-joiner
                        $e = preg_replace("/[\xe2\x80\x8c\xe2\x80\x8d\xe2\x80\x8e\xe2\x80\x8f]/u", '', $e);
                        $e = preg_replace("/[\xef\xbb\xbf]/u", '', $e); // Remove ZWNBSP (also Byte order mark FEFF)
                    }
                }

                if (($this->tts) || ($this->ttz) || ($this->tta)) {
                    $es = explode('|', $e);
                    $e = '';
                    foreach ($es as $val) {
                        $e .= chr($val);
                    }
                }

                //  FORM ELEMENTS
                if ($this->specialcontent) {
                    /* -- FORMS -- */
                    // SELECT tag (form element)
                    if ($this->specialcontent == "type=select") {
                        $e = ltrim($e);
                        if (!empty($this->OTLdata)) {
                            $this->otl->trimOTLdata($this->OTLdata, true, false);
                        } // *OTL*
                        $stringwidth = $this->GetStringWidth($e);
                        if (!isset($this->selectoption['MAXWIDTH']) || $stringwidth > $this->selectoption['MAXWIDTH']) {
                            $this->selectoption['MAXWIDTH'] = $stringwidth;
                        }
                        if (!isset($this->selectoption['SELECTED']) || $this->selectoption['SELECTED'] == '') {
                            $this->selectoption['SELECTED'] = $e;
                            if (!empty($this->OTLdata)) {
                                $this->selectoption['SELECTED-OTLDATA'] = $this->OTLdata;
                            } // *OTL*
                        }
                        // Active Forms
                        if (isset($this->selectoption['ACTIVE']) && $this->selectoption['ACTIVE']) {
                            $this->selectoption['ITEMS'][] = ['exportValue' => $this->selectoption['currentVAL'], 'content' => $e, 'selected' => $this->selectoption['currentSEL']];
                        }
                        $this->OTLdata = [];
                    } // TEXTAREA
                    else {
                        $objattr = unserialize($this->specialcontent);
                        $objattr['text'] = $e;
                        $objattr['OTLdata'] = $this->OTLdata;
                        $this->OTLdata = [];
                        $te = "\xbb\xa4\xactype=textarea,objattr=" . serialize($objattr) . "\xbb\xa4\xac";
                        if ($this->tdbegin) {
                            $this->_saveCellTextBuffer($te, $this->HREF);
                        } else {
                            $this->_saveTextBuffer($te, $this->HREF);
                        }
                    }
                    /* -- END FORMS -- */
                } // TABLE
                elseif ($this->tableLevel) {
                    /* -- TABLES -- */
                    if ($this->tdbegin) {
                        if (($this->ignorefollowingspaces) && !$this->ispre) {
                            $e = ltrim($e);
                            if (!empty($this->OTLdata)) {
                                $this->otl->trimOTLdata($this->OTLdata, true, false);
                            } // *OTL*
                        }
                        if ($e || $e === '0') {
                            if ($this->blockjustfinished && $this->cell[$this->row][$this->col]['s'] > 0) {
                                $this->_saveCellTextBuffer("\n");
                                if (!isset($this->cell[$this->row][$this->col]['maxs'])) {
                                    $this->cell[$this->row][$this->col]['maxs'] = $this->cell[$this->row][$this->col]['s'];
                                } elseif ($this->cell[$this->row][$this->col]['maxs'] < $this->cell[$this->row][$this->col]['s']) {
                                    $this->cell[$this->row][$this->col]['maxs'] = $this->cell[$this->row][$this->col]['s'];
                                }
                                $this->cell[$this->row][$this->col]['s'] = 0; // reset
                            }
                            $this->blockjustfinished = false;

                            if (!isset($this->cell[$this->row][$this->col]['R']) || !$this->cell[$this->row][$this->col]['R']) {
                                if (isset($this->cell[$this->row][$this->col]['s'])) {
                                    $this->cell[$this->row][$this->col]['s'] += $this->GetStringWidth($e, false, $this->OTLdata, $this->textvar);
                                } else {
                                    $this->cell[$this->row][$this->col]['s'] = $this->GetStringWidth($e, false, $this->OTLdata, $this->textvar);
                                }
                                if (!empty($this->spanborddet)) {
                                    $this->cell[$this->row][$this->col]['s'] += (isset($this->spanborddet['L']['w']) ? $this->spanborddet['L']['w'] : 0) + (isset($this->spanborddet['R']['w']) ? $this->spanborddet['R']['w'] : 0);
                                }
                            }
                            $this->_saveCellTextBuffer($e, $this->HREF);
                            if (substr($this->cell[$this->row][$this->col]['a'], 0, 1) == 'D') {
                                $dp = $this->decimal_align[substr($this->cell[$this->row][$this->col]['a'], 0, 2)];
                                $s = preg_split('/' . preg_quote($dp, '/') . '/', $e, 2);  // ? needs to be /u if not core
                                $s0 = $this->GetStringWidth($s[0], false);
                                if (isset($s[1]) && $s[1]) {
                                    $s1 = $this->GetStringWidth(($s[1] . $dp), false);
                                } else {
                                    $s1 = 0;
                                }
                                if (!isset($this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs0'])) {
                                    $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs0'] = $s0;
                                } else {
                                    $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs0'] = max($s0, $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs0']);
                                }
                                if (!isset($this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs1'])) {
                                    $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs1'] = $s1;
                                } else {
                                    $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs1'] = max($s1, $this->table[$this->tableLevel][$this->tbctr[$this->tableLevel]]['decimal_align'][$this->col]['maxs1']);
                                }
                            }

                            $this->nestedtablejustfinished = false;
                            $this->linebreakjustfinished = false;
                        }
                    }
                    /* -- END TABLES -- */
                } // ALL ELSE
                else {
                    if ($this->ignorefollowingspaces && !$this->ispre) {
                        $e = ltrim($e);
                        if (!empty($this->OTLdata)) {
                            $this->otl->trimOTLdata($this->OTLdata, true, false);
                        } // *OTL*
                    }
                    if ($e || $e === '0') {
                        $this->_saveTextBuffer($e, $this->HREF);
                    }
                }
                if ($e || $e === '0') {
                    $this->ignorefollowingspaces = false; // mPDF 6
                }
                if (substr($e, -1, 1) == ' ' && !$this->ispre && $this->FontFamily != 'csymbol' && $this->FontFamily != 'czapfdingbats') {
                    $this->ignorefollowingspaces = true;
                }
            } else { // TAG **
                if (isset($e[0]) && $e[0] == '/') {
                    $endtag = trim(strtoupper(substr($e, 1)));

                    /* -- CSS-POSITION -- */
                    // mPDF 6
                    if ($this->inFixedPosBlock) {
                        if (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags)) {
                            $this->fixedPosBlockDepth--;
                        }
                        if ($this->fixedPosBlockDepth == 0) {
                            $this->fixedPosBlockSave[] = [$this->fixedPosBlock, $this->fixedPosBlockBBox, $this->page];
                            $this->fixedPosBlock = '';
                            $this->inFixedPosBlock = false;
                            continue;
                        }
                        $this->fixedPosBlock .= '<' . $e . '>';
                        continue;
                    }
                    /* -- END CSS-POSITION -- */

                    // mPDF 6
                    // Correct for tags where HTML5 specifies optional end tags (see also OpenTag() )
                    if ($this->allow_html_optional_endtags && !$parseonly) {
                        if (isset($this->blk[$this->blklvl]['tag'])) {
                            $closed = false;
                            // li end tag may be omitted if there is no more content in the parent element
                            if (!$closed && $this->blk[$this->blklvl]['tag'] == 'LI' && $endtag != 'LI' && (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags))) {
                                $this->tag->CloseTag('LI', $a, $i);
                                $closed = true;
                            }
                            // dd end tag may be omitted if there is no more content in the parent element
                            if (!$closed && $this->blk[$this->blklvl]['tag'] == 'DD' && $endtag != 'DD' && (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags))) {
                                $this->tag->CloseTag('DD', $a, $i);
                                $closed = true;
                            }
                            // p end tag may be omitted if there is no more content in the parent element and the parent element is not an A element [??????]
                            if (!$closed && $this->blk[$this->blklvl]['tag'] == 'P' && $endtag != 'P' && (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags))) {
                                $this->tag->CloseTag('P', $a, $i);
                                $closed = true;
                            }
                            // option end tag may be omitted if there is no more content in the parent element
                            if (!$closed && $this->blk[$this->blklvl]['tag'] == 'OPTION' && $endtag != 'OPTION' && (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags))) {
                                $this->tag->CloseTag('OPTION', $a, $i);
                                $closed = true;
                            }
                        }
                        /* -- TABLES -- */
                        // Check for Table tags where HTML specifies optional end tags,
                        if ($endtag == 'TABLE') {
                            if ($this->lastoptionaltag == 'THEAD' || $this->lastoptionaltag == 'TBODY' || $this->lastoptionaltag == 'TFOOT') {
                                $this->tag->CloseTag($this->lastoptionaltag, $a, $i);
                            }
                            if ($this->lastoptionaltag == 'TR') {
                                $this->tag->CloseTag('TR', $a, $i);
                            }
                            if ($this->lastoptionaltag == 'TD' || $this->lastoptionaltag == 'TH') {
                                $this->tag->CloseTag($this->lastoptionaltag, $a, $i);
                                $this->tag->CloseTag('TR', $a, $i);
                            }
                        }
                        if ($endtag == 'THEAD' || $endtag == 'TBODY' || $endtag == 'TFOOT') {
                            if ($this->lastoptionaltag == 'TR') {
                                $this->tag->CloseTag('TR', $a, $i);
                            }
                            if ($this->lastoptionaltag == 'TD' || $this->lastoptionaltag == 'TH') {
                                $this->tag->CloseTag($this->lastoptionaltag, $a, $i);
                                $this->tag->CloseTag('TR', $a, $i);
                            }
                        }
                        if ($endtag == 'TR') {
                            if ($this->lastoptionaltag == 'TD' || $this->lastoptionaltag == 'TH') {
                                $this->tag->CloseTag($this->lastoptionaltag, $a, $i);
                            }
                        }
                        /* -- END TABLES -- */
                    }


                    // mPDF 6
                    if ($this->blk[$this->blklvl]['hide']) {
                        if (in_array($endtag, $this->outerblocktags) || in_array($endtag, $this->innerblocktags)) {
                            unset($this->blk[$this->blklvl]);
                            $this->blklvl--;
                        }
                        continue;
                    }

                    // mPDF 6
                    $this->tag->CloseTag($endtag, $a, $i); // mPDF 6
                } else { // OPENING TAG
                    if ($this->blk[$this->blklvl]['hide']) {
                        if (strpos($e, ' ')) {
                            $te = strtoupper(substr($e, 0, strpos($e, ' ')));
                        } else {
                            $te = strtoupper($e);
                        }
                        // mPDF 6
                        if ($te == 'THEAD' || $te == 'TBODY' || $te == 'TFOOT' || $te == 'TR' || $te == 'TD' || $te == 'TH') {
                            $this->lastoptionaltag = $te;
                        }
                        if (in_array($te, $this->outerblocktags) || in_array($te, $this->innerblocktags)) {
                            $this->blklvl++;
                            $this->blk[$this->blklvl]['hide'] = true;
                            $this->blk[$this->blklvl]['tag'] = $te; // mPDF 6
                        }
                        continue;
                    }

                    /* -- CSS-POSITION -- */
                    if ($this->inFixedPosBlock) {
                        if (strpos($e, ' ')) {
                            $te = strtoupper(substr($e, 0, strpos($e, ' ')));
                        } else {
                            $te = strtoupper($e);
                        }
                        $this->fixedPosBlock .= '<' . $e . '>';
                        if (in_array($te, $this->outerblocktags) || in_array($te, $this->innerblocktags)) {
                            $this->fixedPosBlockDepth++;
                        }
                        continue;
                    }
                    /* -- END CSS-POSITION -- */
                    $regexp = '|=\'(.*?)\'|s'; // eliminate single quotes, if any
                    $e = preg_replace($regexp, "=\"\$1\"", $e);
                    // changes anykey=anyvalue to anykey="anyvalue" (only do this inside [some] tags)
                    if (substr($e, 0, 10) != 'pageheader' && substr($e, 0, 10) != 'pagefooter' && substr($e, 0, 12) != 'tocpagebreak' && substr($e, 0, 10) != 'indexentry' && substr($e, 0, 8) != 'tocentry') { // mPDF 6  (ZZZ99H)
                        $regexp = '| (\\w+?)=([^\\s>"]+)|si';
                        $e = preg_replace($regexp, " \$1=\"\$2\"", $e);
                    }

                    $e = preg_replace('/ (\\S+?)\s*=\s*"/i', " \\1=\"", $e);

                    // Fix path values, if needed
                    $orig_srcpath = '';
                    if ((stristr($e, "href=") !== false) or ( stristr($e, "src=") !== false)) {
                        $regexp = '/ (href|src)\s*=\s*"(.*?)"/i';
                        preg_match($regexp, $e, $auxiliararray);
                        if (isset($auxiliararray[2])) {
                            $path = $auxiliararray[2];
                        } else {
                            $path = '';
                        }
                        if (trim($path) != '' && !(stristr($e, "src=") !== false && substr($path, 0, 4) == 'var:') && substr($path, 0, 1) != '@') {
                            $path = htmlspecialchars_decode($path); // mPDF 5.7.4 URLs
                            $orig_srcpath = $path;
                            $this->GetFullPath($path);
                            $regexp = '/ (href|src)="(.*?)"/i';
                            $e = preg_replace($regexp, ' \\1="' . $path . '"', $e);
                        }
                    }//END of Fix path values
                    // Extract attributes
                    $contents = [];
                    $contents1 = [];
                    $contents2 = [];
                    // Changed to allow style="background: url('bg.jpg')"
                    // Changed to improve performance; maximum length of \S (attribute) = 16
                    // Increase allowed attribute name to 32 - cutting off "toc-even-header-name" etc.
                    preg_match_all('/\\S{1,32}=["][^"]*["]/', $e, $contents1);
                    preg_match_all('/\\S{1,32}=[\'][^\']*[\']/i', $e, $contents2);

                    $contents = array_merge($contents1, $contents2);
                    preg_match('/\\S+/', $e, $a2);
                    $tag = (isset($a2[0]) ? strtoupper($a2[0]) : '');
                    $attr = [];
                    if ($orig_srcpath) {
                        $attr['ORIG_SRC'] = $orig_srcpath;
                    }
                    if (!empty($contents)) {
                        foreach ($contents[0] as $v) {
                            // Changed to allow style="background: url('bg.jpg')"
                            if (preg_match('/^([^=]*)=["]?([^"]*)["]?$/', $v, $a3) || preg_match('/^([^=]*)=[\']?([^\']*)[\']?$/', $v, $a3)) {
                                if (strtoupper($a3[1]) == 'ID' || strtoupper($a3[1]) == 'CLASS') { // 4.2.013 Omits STYLE
                                    $attr[strtoupper($a3[1])] = trim(strtoupper($a3[2]));
                                } // includes header-style-right etc. used for <pageheader>
                                elseif (preg_match('/^(HEADER|FOOTER)-STYLE/i', $a3[1])) {
                                    $attr[strtoupper($a3[1])] = trim(strtoupper($a3[2]));
                                } else {
                                    $attr[strtoupper($a3[1])] = trim($a3[2]);
                                }
                            }
                        }
                    }
                    $this->tag->OpenTag($tag, $attr, $a, $i); // mPDF 6
                    /* -- CSS-POSITION -- */
                    if ($this->inFixedPosBlock) {
                        $this->fixedPosBlockBBox = [$tag, $attr, $this->x, $this->y];
                        $this->fixedPosBlock = '';
                        $this->fixedPosBlockDepth = 1;
                    }
                    /* -- END CSS-POSITION -- */
                    if (preg_match('/\/$/', $e)) {
                        $this->tag->CloseTag($tag, $a, $i);
                    }
                }
            } // end TAG
        } // end of foreach($a as $i=>$e)

        if ($close) {
            // Close any open block tags
            for ($b = $this->blklvl; $b > 0; $b--) {
                $this->tag->CloseTag($this->blk[$b]['tag'], $a, $i);
            }

            // Output any text left in buffer
            if (count($this->textbuffer) && !$parseonly) {
                $this->printbuffer($this->textbuffer);
            }
            if (!$parseonly) {
                $this->textbuffer = [];
            }

            /* -- CSS-FLOAT -- */
            // If ended with a float, need to move to end page
            $currpos = $this->page * 1000 + $this->y;
            if (isset($this->blk[$this->blklvl]['float_endpos']) && $this->blk[$this->blklvl]['float_endpos'] > $currpos) {
                $old_page = $this->page;
                $new_page = intval($this->blk[$this->blklvl]['float_endpos'] / 1000);
                if ($old_page != $new_page) {
                    $s = $this->PrintPageBackgrounds();
                    // Writes after the marker so not overwritten later by page background etc.
                    $this->pages[$this->page] = preg_replace('/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->pages[$this->page]);
                    $this->pageBackgrounds = [];
                    $this->page = $new_page;
                    $this->ResetMargins();
                    $this->Reset();
                    $this->pageoutput[$this->page] = [];
                }
                $this->y = (($this->blk[$this->blklvl]['float_endpos'] * 1000) % 1000000) / 1000; // mod changes operands to integers before processing
            }
            /* -- END CSS-FLOAT -- */

            /* -- CSS-IMAGE-FLOAT -- */
            $this->printfloatbuffer();
            /* -- END CSS-IMAGE-FLOAT -- */

            // Create Internal Links, if needed
            if (!empty($this->internallink)) {
                foreach ($this->internallink as $k => $v) {
                    if (strpos($k, "#") !== false) {
                        continue;
                    } // ignore
                    $ypos = $v['Y'];
                    $pagenum = $v['PAGE'];
                    $sharp = "#";
                    while (array_key_exists($sharp . $k, $this->internallink)) {
                        $internallink = $this->internallink[$sharp . $k];
                        $this->SetLink($internallink, $ypos, $pagenum);
                        $sharp .= "#";
                    }
                }
            }

            $this->bufferoutput = false;

            /* -- CSS-POSITION -- */
            if (count($this->fixedPosBlockSave)) {
                foreach ($this->fixedPosBlockSave as $fpbs) {
                    $old_page = $this->page;
                    $this->page = $fpbs[2];
                    $this->WriteFixedPosHTML($fpbs[0], 0, 0, 100, 100, 'auto', $fpbs[1]);  // 0,0,10,10 are overwritten by bbox
                    $this->page = $old_page;
                }
                $this->fixedPosBlockSave = [];
            }
            /* -- END CSS-POSITION -- */
        }
    }

    /* -- CSS-POSITION -- */

    public function WriteFixedPosHTML($html, $x, $y, $w, $h, $overflow = 'visible', $bounding = [])
    {
        // $overflow can be 'hidden', 'visible' or 'auto' - 'auto' causes autofit to size
        // Annotations disabled - enabled in mPDF 5.0
        // Links do work
        // Will always go on current page (or start Page 1 if required)
        // Probably INCOMPATIBLE WITH keep with table, columns etc.
        // Called externally or interally via <div style="position: [fixed|absolute]">
        // When used internally, $x $y $w $h and $overflow are all overridden by $bounding

        $overflow = strtolower($overflow);
        if ($this->state == 0) {
            $this->AddPage($this->CurOrientation);
        }
        $save_y = $this->y;
        $save_x = $this->x;
        $this->fullImageHeight = $this->h;
        $save_cols = false;
        /* -- COLUMNS -- */
        if ($this->ColActive) {
            $save_cols = true;
            $save_nbcol = $this->NbCol; // other values of gap and vAlign will not change by setting Columns off
            $this->SetColumns(0);
        }
        /* -- END COLUMNS -- */
        $save_annots = $this->title2annots; // *ANNOTATIONS*
        $this->writingHTMLheader = true; // a FIX to stop pagebreaks etc.
        $this->writingHTMLfooter = true;
        $this->InFooter = true; // suppresses autopagebreaks
        $save_bgs = $this->pageBackgrounds;
        $checkinnerhtml = preg_replace('/\s/', '', $html);
        $rotate = 0;

        if ($w > $this->w) {
            $x = 0;
            $w = $this->w;
        }
        if ($h > $this->h) {
            $y = 0;
            $h = $this->h;
        }
        if ($x > $this->w) {
            $x = $this->w - $w;
        }
        if ($y > $this->h) {
            $y = $this->h - $h;
        }

        if (!empty($bounding)) {
            // $cont_ containing block = full physical page (position: absolute) or page inside margins (position: fixed)
            // $bbox_ Bounding box is the <div> which is positioned absolutely/fixed
            // top/left/right/bottom/width/height/background*/border*/padding*/margin* are taken from bounding
            // font*[family/size/style/weight]/line-height/text*[align/decoration/transform/indent]/color are transferred to $inner
            // as an enclosing <div> (after having checked ID/CLASS)
            // $x, $y, $w, $h are inside of $bbox_ = containing box for $inner_
            // $inner_ InnerHTML is the contents of that block to be output
            $tag = $bounding[0];
            $attr = $bounding[1];
            $orig_x0 = $bounding[2];
            $orig_y0 = $bounding[3];

            // As in WriteHTML() initialising
            $this->blklvl = 0;
            $this->lastblocklevelchange = 0;
            $this->blk = [];
            $this->initialiseBlock($this->blk[0]);

            $this->blk[0]['width'] = & $this->pgwidth;
            $this->blk[0]['inner_width'] = & $this->pgwidth;

            $this->blk[0]['blockContext'] = $this->blockContext;

            $properties = $this->cssManager->MergeCSS('BLOCK', 'BODY', '');
            $this->setCSS($properties, '', 'BODY');
            $this->blklvl = 1;
            $this->initialiseBlock($this->blk[1]);
            $this->blk[1]['tag'] = $tag;
            $this->blk[1]['attr'] = $attr;
            $this->Reset();
            $p = $this->cssManager->MergeCSS('BLOCK', $tag, $attr);
            if (isset($p['ROTATE']) && ($p['ROTATE'] == 90 || $p['ROTATE'] == -90 || $p['ROTATE'] == 180)) {
                $rotate = $p['ROTATE'];
            } // mPDF 6
            if (isset($p['OVERFLOW'])) {
                $overflow = strtolower($p['OVERFLOW']);
            }
            if (strtolower($p['POSITION']) == 'fixed') {
                $cont_w = $this->pgwidth; // $this->blk[0]['inner_width'];
                $cont_h = $this->h - $this->tMargin - $this->bMargin;
                $cont_x = $this->lMargin;
                $cont_y = $this->tMargin;
            } else {
                $cont_w = $this->w; // ABSOLUTE;
                $cont_h = $this->h;
                $cont_x = 0;
                $cont_y = 0;
            }

            $css_props = [
                'TEXT-ALIGN',
                'TEXT-TRANSFORM',
                'TEXT-INDENT',
                'TEXT-DECORATION',
                'FONT-FAMILY',
                'FONT-STYLE',
                'FONT-WEIGHT',
                'FONT-SIZE',
                'LINE-HEIGHT',
                'TEXT-SHADOW',
                'LETTER-SPACING',

                // mPDF 6
                'FONT-VARIANT-POSITION',
                'FONT-VARIANT-CAPS',
                'FONT-VARIANT-LIGATURES',
                'FONT-VARIANT-NUMERIC',
                'FONT-VARIANT-ALTERNATES',
                'FONT-FEATURE-SETTINGS',
                'FONT-LANGUAGE-OVERRIDE',
                'FONT-KERNING',
                'COLOR',
                'Z-INDEX',
            ];

            // Pass on in-line properties to the innerhtml
            $css = '';
            foreach($css_props as $prop){
                if (isset($p[$prop])) {
                    $css .= mb_strtolower($prop) . ': ' . strtolower($p[$prop]) . '; ';
                }
            }

            if ($css) {
                $html = '<div style="' . $css . '">' . $html . '</div>';
            }

            // Copy over (only) the properties to set for border and background
            $pb = [];

            $props_to_set_or_empty = [
                'MARGIN-TOP',
                'MARGIN-RIGHT',
                'MARGIN-BOTTOM',
                'MARGIN-LEFT',
                'PADDING-TOP',
                'PADDING-RIGHT',
                'PADDING-BOTTOM',
                'PADDING-LEFT',
                'BORDER-TOP',
                'BORDER-RIGHT',
                'BORDER-BOTTOM',
                'BORDER-LEFT',
            ];

            foreach($props_to_set_or_empty as $prop){
                $pb[$prop] = (isset($p[$prop]) ? $p[$prop] : '');
            }

            $only_if_set_props = [
                'BORDER-TOP-LEFT-RADIUS-H',
                'BORDER-TOP-LEFT-RADIUS-V',
                'BORDER-TOP-RIGHT-RADIUS-H',
                'BORDER-TOP-RIGHT-RADIUS-V',
                'BORDER-BOTTOM-LEFT-RADIUS-H',
                'BORDER-BOTTOM-LEFT-RADIUS-V',
                'BORDER-BOTTOM-RIGHT-RADIUS-H',
                'BORDER-BOTTOM-RIGHT-RADIUS-V',
                'BACKGROUND-COLOR',
                'BOX-SHADOW',
                'BACKGROUND-IMAGE',
                'BACKGROUND-IMAGE-RESIZE',
                'BACKGROUND-IMAGE-OPACITY',
                'BACKGROUND-REPEAT',
                'BACKGROUND-POSITION',
                'BACKGROUND-GRADIENT',
                'BACKGROUND-SIZE',
                'BACKGROUND-ORIGIN',
                'BACKGROUND-CLIP',
            ];

            foreach($only_if_set_props as $prop){
                if (isset($p[$prop])) {
                    $pb[$prop] = $p[$prop];
                }
            }

            /* -- END BACKGROUNDS -- */

            $this->setCSS($pb, 'BLOCK', $tag);

            // ================================================================
            $bbox_br = $this->blk[1]['border_right']['w'];
            $bbox_bl = $this->blk[1]['border_left']['w'];
            $bbox_bt = $this->blk[1]['border_top']['w'];
            $bbox_bb = $this->blk[1]['border_bottom']['w'];

            $bbox_pr = $this->blk[1]['padding_right'];
            $bbox_pl = $this->blk[1]['padding_left'];
            $bbox_pt = $this->blk[1]['padding_top'];
            $bbox_pb = $this->blk[1]['padding_bottom'];

            $bbox_mr = $this->blk[1]['margin_right'];
            $bbox_ml = $this->blk[1]['margin_left'];
            $bbox_mt = $this->blk[1]['margin_top'];
            $bbox_mb = $this->blk[1]['margin_bottom'];

            if (isset($p['MARGIN-RIGHT']) && strtolower($p['MARGIN-RIGHT']) == 'auto') {
                $bbox_mr = 'auto';
            }
            if (isset($p['MARGIN-LEFT']) && strtolower($p['MARGIN-LEFT']) == 'auto') {
                $bbox_ml = 'auto';
            }
            if (isset($p['MARGIN-TOP']) && strtolower($p['MARGIN-TOP']) == 'auto') {
                $bbox_mt = 'auto';
            }
            if (isset($p['MARGIN-BOTTOM']) && strtolower($p['MARGIN-BOTTOM']) == 'auto') {
                $bbox_mb = 'auto';
            }

            $bbox_left = 'auto';
            $bbox_top = 'auto';
            $bbox_right = 'auto';
            $bbox_bottom = 'auto';
            $inner_w = 'auto';
            $inner_h = 'auto';

            $box_auto_props = [
                [ &$bbox_left,      'LEFT',     $cont_w ],
                [ &$bbox_top,       'TOP',      $cont_h ],
                [ &$bbox_right,     'RIGHT',    $cont_w ],
                [ &$bbox_bottom,    'BOTTOM',   $cont_h ],
                [ &$inner_w,        'WIDTH',    $cont_w ],
                [ &$inner_h,        'HEIGHT',   $cont_h ],
            ];

            foreach($box_auto_props as $item){
                $var  = &$item[0];
                $prop =  $item[1];
                $cont =  $item[2];
                if (isset($p[$prop]) && strtolower($p[$prop]) != 'auto') {
                    $var = $this->sizeConverter->convert($p[$prop], $cont, $this->FontSize, false);
                }
            }

            // If bottom or right pos are set and not left / top - save this to adjust rotated block later
            if ($rotate == 90 || $rotate == -90) { // mPDF 6
                if ($bbox_left === 'auto' && $bbox_right !== 'auto') {
                    $rot_rpos = $bbox_right;
                } else {
                    $rot_rpos = false;
                }
                if ($bbox_top === 'auto' && $bbox_bottom !== 'auto') {
                    $rot_bpos = $bbox_bottom;
                } else {
                    $rot_bpos = false;
                }
            }

            // ================================================================
            if ($checkinnerhtml == '' && $inner_h === 'auto') {
                $inner_h = 0.0001;
            }
            if ($checkinnerhtml == '' && $inner_w === 'auto') {
                $inner_w = 2 * $this->GetCharWidth('W', false);
            }
            // ================================================================
            // Algorithm from CSS2.1  See http://www.w3.org/TR/CSS21/visudet.html#abs-non-replaced-height
            // mPD 5.3.14
            // Special case (not CSS) if all not specified, centre vertically on page
            $bbox_top_orig = '';
            if ($bbox_top === 'auto' && $inner_h === 'auto' && $bbox_bottom === 'auto' && $bbox_mt === 'auto' && $bbox_mb === 'auto') {
                $bbox_top_orig = $bbox_top;
                if ($bbox_mt === 'auto') {
                    $bbox_mt = 0;
                }
                if ($bbox_mb === 'auto') {
                    $bbox_mb = 0;
                }
                $bbox_top = $orig_y0 - $bbox_mt - $cont_y;
                // solve for $bbox_bottom when content_h known - $inner_h=='auto' && $bbox_bottom=='auto'
            } // mPD 5.3.14
            elseif ($bbox_top === 'auto' && $inner_h === 'auto' && $bbox_bottom === 'auto') {
                $bbox_top_orig = $bbox_top = $orig_y0 - $cont_y;
                if ($bbox_mt === 'auto') {
                    $bbox_mt = 0;
                }
                if ($bbox_mb === 'auto') {
                    $bbox_mb = 0;
                }
                // solve for $bbox_bottom when content_h known - $inner_h=='auto' && $bbox_bottom=='auto'
            } elseif ($bbox_top !== 'auto' && $inner_h !== 'auto' && $bbox_bottom !== 'auto') {
                if ($bbox_mt === 'auto' && $bbox_mb === 'auto') {
                    $x = $cont_h - $bbox_top - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_bottom;
                    $bbox_mt = $bbox_mb = ($x / 2);
                } elseif ($bbox_mt === 'auto') {
                    $bbox_mt = $cont_h - $bbox_top - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mb - $bbox_bottom;
                } elseif ($bbox_mb === 'auto') {
                    $bbox_mb = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_bottom;
                } else {
                    $bbox_bottom = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mt;
                }
            } else {
                if ($bbox_mt === 'auto') {
                    $bbox_mt = 0;
                }
                if ($bbox_mb === 'auto') {
                    $bbox_mb = 0;
                }
                if ($bbox_top === 'auto' && $inner_h === 'auto' && $bbox_bottom !== 'auto') {
                    // solve for $bbox_top when content_h known - $inner_h=='auto' && $bbox_top =='auto'
                } elseif ($bbox_top === 'auto' && $bbox_bottom === 'auto' && $inner_h !== 'auto') {
                    $bbox_top = $orig_y0 - $bbox_mt - $cont_y;
                    $bbox_bottom = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mt;
                } elseif ($inner_h === 'auto' && $bbox_bottom === 'auto' && $bbox_top !== 'auto') {
                    // solve for $bbox_bottom when content_h known - $inner_h=='auto' && $bbox_bottom=='auto'
                } elseif ($bbox_top === 'auto' && $inner_h !== 'auto' && $bbox_bottom !== 'auto') {
                    $bbox_top = $cont_h - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mt - $bbox_bottom;
                } elseif ($inner_h === 'auto' && $bbox_top !== 'auto' && $bbox_bottom !== 'auto') {
                    $inner_h = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $bbox_pb - $bbox_bb - $bbox_mt - $bbox_bottom;
                } elseif ($bbox_bottom === 'auto' && $bbox_top !== 'auto' && $inner_h !== 'auto') {
                    $bbox_bottom = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mt;
                }
            }

            // THEN DO SAME FOR WIDTH
            // http://www.w3.org/TR/CSS21/visudet.html#abs-non-replaced-width
            if ($bbox_left === 'auto' && $inner_w === 'auto' && $bbox_right === 'auto') {
                if ($bbox_ml === 'auto') {
                    $bbox_ml = 0;
                }
                if ($bbox_mr === 'auto') {
                    $bbox_mr = 0;
                }
                // IF containing element RTL, should set $bbox_right
                $bbox_left = $orig_x0 - $bbox_ml - $cont_x;
                // solve for $bbox_right when content_w known - $inner_w=='auto' && $bbox_right=='auto'
            } elseif ($bbox_left !== 'auto' && $inner_w !== 'auto' && $bbox_right !== 'auto') {
                if ($bbox_ml === 'auto' && $bbox_mr === 'auto') {
                    $x = $cont_w - $bbox_left - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_right;
                    $bbox_ml = $bbox_mr = ($x / 2);
                } elseif ($bbox_ml === 'auto') {
                    $bbox_ml = $cont_w - $bbox_left - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_mr - $bbox_right;
                } elseif ($bbox_mr === 'auto') {
                    $bbox_mr = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_right;
                } else {
                    $bbox_right = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml;
                }
            } else {
                if ($bbox_ml === 'auto') {
                    $bbox_ml = 0;
                }
                if ($bbox_mr === 'auto') {
                    $bbox_mr = 0;
                }
                if ($bbox_left === 'auto' && $inner_w === 'auto' && $bbox_right !== 'auto') {
                    // solve for $bbox_left when content_w known - $inner_w=='auto' && $bbox_left =='auto'
                } elseif ($bbox_left === 'auto' && $bbox_right === 'auto' && $inner_w !== 'auto') {
                    // IF containing element RTL, should set $bbox_right
                    $bbox_left = $orig_x0 - $bbox_ml - $cont_x;
                    $bbox_right = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml;
                } elseif ($inner_w === 'auto' && $bbox_right === 'auto' && $bbox_left !== 'auto') {
                    // solve for $bbox_right when content_w known - $inner_w=='auto' && $bbox_right=='auto'
                } elseif ($bbox_left === 'auto' && $inner_w !== 'auto' && $bbox_right !== 'auto') {
                    $bbox_left = $cont_w - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml - $bbox_right;
                } elseif ($inner_w === 'auto' && $bbox_left !== 'auto' && $bbox_right !== 'auto') {
                    $inner_w = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $bbox_pr - $bbox_br - $bbox_ml - $bbox_right;
                } elseif ($bbox_right === 'auto' && $bbox_left !== 'auto' && $inner_w !== 'auto') {
                    $bbox_right = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml;
                }
            }

            // ================================================================
            // ================================================================
            /* -- BACKGROUNDS -- */
            if (isset($pb['BACKGROUND-IMAGE']) && $pb['BACKGROUND-IMAGE']) {
                $ret = $this->SetBackground($pb, $this->blk[1]['inner_width']);
                if ($ret) {
                    $this->blk[1]['background-image'] = $ret;
                }
            }
            /* -- END BACKGROUNDS -- */

            $bbox_top_auto = $bbox_top === 'auto';
            $bbox_left_auto = $bbox_left === 'auto';
            $bbox_right_auto = $bbox_right === 'auto';
            $bbox_bottom_auto = $bbox_bottom === 'auto';

            $bbox_top = is_numeric($bbox_top) ? $bbox_top : 0;
            $bbox_left = is_numeric($bbox_left) ? $bbox_left : 0;
            $bbox_right = is_numeric($bbox_right) ? $bbox_right : 0;
            $bbox_bottom = is_numeric($bbox_bottom) ? $bbox_bottom : 0;

            $y = $cont_y + $bbox_top + $bbox_mt + $bbox_bt + $bbox_pt;
            $h = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $bbox_pb - $bbox_bb - $bbox_mb - $bbox_bottom;

            $x = $cont_x + $bbox_left + $bbox_ml + $bbox_bl + $bbox_pl;
            $w = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $bbox_pr - $bbox_br - $bbox_mr - $bbox_right;

            // Set (temporary) values for x y w h to do first paint, if values are auto
            if ($inner_h === 'auto' && $bbox_top_auto) {
                $y = $cont_y + $bbox_mt + $bbox_bt + $bbox_pt;
                $h = $cont_h - ($bbox_bottom + $bbox_mt + $bbox_mb + $bbox_bt + $bbox_bb + $bbox_pt + $bbox_pb);
            } elseif ($inner_h === 'auto' && $bbox_bottom_auto) {
                $y = $cont_y + $bbox_top + $bbox_mt + $bbox_bt + $bbox_pt;
                $h = $cont_h - ($bbox_top + $bbox_mt + $bbox_mb + $bbox_bt + $bbox_bb + $bbox_pt + $bbox_pb);
            }
            if ($inner_w === 'auto' && $bbox_left_auto) {
                $x = $cont_x + $bbox_ml + $bbox_bl + $bbox_pl;
                $w = $cont_w - ($bbox_right + $bbox_ml + $bbox_mr + $bbox_bl + $bbox_br + $bbox_pl + $bbox_pr);
            } elseif ($inner_w === 'auto' && $bbox_right_auto) {
                $x = $cont_x + $bbox_left + $bbox_ml + $bbox_bl + $bbox_pl;
                $w = $cont_w - ($bbox_left + $bbox_ml + $bbox_mr + $bbox_bl + $bbox_br + $bbox_pl + $bbox_pr);
            }

            $bbox_y = $cont_y + $bbox_top + $bbox_mt;
            $bbox_x = $cont_x + $bbox_left + $bbox_ml;

            $saved_block1 = $this->blk[1];

            unset($p);
            unset($pb);

            // ================================================================
            if ($inner_w === 'auto') { // do a first write
                $this->lMargin = $x;
                $this->rMargin = $this->w - $w - $x;

                // SET POSITION & FONT VALUES
                $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
                $this->pageoutput[$this->page] = [];
                $this->x = $x;
                $this->y = $y;
                $this->HTMLheaderPageLinks = [];
                $this->HTMLheaderPageAnnots = [];
                $this->HTMLheaderPageForms = [];
                $this->pageBackgrounds = [];
                $this->maxPosR = 0;
                $this->maxPosL = $this->w; // For RTL
                $this->WriteHTML($html, 4);
                $inner_w = $this->maxPosR - $this->lMargin;
                if ($bbox_right_auto) {
                    $bbox_right = $cont_w - $bbox_left - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml;
                } elseif ($bbox_left_auto) {
                    $bbox_left = $cont_w - $bbox_ml - $bbox_bl - $bbox_pl - $inner_w - $bbox_pr - $bbox_br - $bbox_ml - $bbox_right;
                    $bbox_x = $cont_x + $bbox_left + $bbox_ml;
                    $inner_x = $bbox_x + $bbox_bl + $bbox_pl;
                    $x = $inner_x;
                }

                $w = $inner_w;
                $bbox_y = $cont_y + $bbox_top + $bbox_mt;
                $bbox_x = $cont_x + $bbox_left + $bbox_ml;
            }

            if ($inner_h === 'auto') { // do a first write

                $this->lMargin = $x;
                $this->rMargin = $this->w - $w - $x;

                // SET POSITION & FONT VALUES
                $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
                $this->pageoutput[$this->page] = [];
                $this->x = $x;
                $this->y = $y;
                $this->HTMLheaderPageLinks = [];
                $this->HTMLheaderPageAnnots = [];
                $this->HTMLheaderPageForms = [];
                $this->pageBackgrounds = [];
                $this->WriteHTML($html, 4);
                $inner_h = $this->y - $y;

                if ($overflow != 'hidden' && $overflow != 'visible') { // constrained
                    if (($this->y + $bbox_pb + $bbox_bb) > ($cont_y + $cont_h)) {
                        $adj = ($this->y + $bbox_pb + $bbox_bb) - ($cont_y + $cont_h);
                        $inner_h -= $adj;
                    }
                }
                if ($bbox_bottom_auto && $bbox_top_orig === 'auto') {
                    $bbox_bottom = $bbox_top = ($cont_h - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mb) / 2;
                    if ($overflow != 'hidden' && $overflow != 'visible') { // constrained
                        if ($bbox_top < 0) {
                            $bbox_top = 0;
                            $inner_h = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $bbox_pb - $bbox_bb - $bbox_mb - $bbox_bottom;
                        }
                    }
                    $bbox_y = $cont_y + $bbox_top + $bbox_mt;
                    $inner_y = $bbox_y + $bbox_bt + $bbox_pt;
                    $y = $inner_y;
                } elseif ($bbox_bottom_auto) {
                    $bbox_bottom = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mb;
                } elseif ($bbox_top_auto) {
                    $bbox_top = $cont_h - $bbox_mt - $bbox_bt - $bbox_pt - $inner_h - $bbox_pb - $bbox_bb - $bbox_mb - $bbox_bottom;
                    if ($overflow != 'hidden' && $overflow != 'visible') { // constrained
                        if ($bbox_top < 0) {
                            $bbox_top = 0;
                            $inner_h = $cont_h - $bbox_top - $bbox_mt - $bbox_bt - $bbox_pt - $bbox_pb - $bbox_bb - $bbox_mb - $bbox_bottom;
                        }
                    }
                    $bbox_y = $cont_y + $bbox_top + $bbox_mt;
                    $inner_y = $bbox_y + $bbox_bt + $bbox_pt;
                    $y = $inner_y;
                }
                $h = $inner_h;
                $bbox_y = $cont_y + $bbox_top + $bbox_mt;
                $bbox_x = $cont_x + $bbox_left + $bbox_ml;
            }

            $inner_w = $w;
            $inner_h = $h;
        }

        $this->lMargin = $x;
        $this->rMargin = $this->w - $w - $x;

        // SET POSITION & FONT VALUES
        $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
        $this->pageoutput[$this->page] = [];

        $this->x = $x;
        $this->y = $y;

        $this->HTMLheaderPageLinks = [];
        $this->HTMLheaderPageAnnots = [];
        $this->HTMLheaderPageForms = [];

        $this->pageBackgrounds = [];

        $this->WriteHTML($html, 4); // parameter 4 saves output to $this->headerbuffer

        $actual_h = $this->y - $y;
        $use_w = $w;
        $use_h = $h;
        $ratio = $actual_h / $use_w;

        if ($overflow != 'hidden' && $overflow != 'visible') {
            $target = $h / $w;
            if (($ratio / $target ) > 1) {
                $nl = ceil($actual_h / $this->lineheight);
                $l = $use_w * $nl;
                $est_w = sqrt(($l * $this->lineheight) / $target) * 0.8;
                $use_w += ($est_w - $use_w) - ($w / 100);
            }
            $bpcstart = ($ratio / $target);
            $bpcctr = 1;

            while (($ratio / $target ) > 1) {
                // @log 'Auto-sizing fixed-position block $bpcctr++

                $this->x = $x;
                $this->y = $y;

                if (($ratio / $target) > 1.5 || ($ratio / $target) < 0.6) {
                    $use_w += ($w / $this->incrementFPR1);
                } elseif (($ratio / $target) > 1.2 || ($ratio / $target) < 0.85) {
                    $use_w += ($w / $this->incrementFPR2);
                } elseif (($ratio / $target) > 1.1 || ($ratio / $target) < 0.91) {
                    $use_w += ($w / $this->incrementFPR3);
                } else {
                    $use_w += ($w / $this->incrementFPR4);
                }

                $use_h = $use_w * $target;
                $this->rMargin = $this->w - $use_w - $x;
                $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
                $this->HTMLheaderPageLinks = [];
                $this->HTMLheaderPageAnnots = [];
                $this->HTMLheaderPageForms = [];
                $this->pageBackgrounds = [];
                $this->WriteHTML($html, 4); // parameter 4 saves output to $this->headerbuffer
                $actual_h = $this->y - $y;
                $ratio = $actual_h / $use_w;
            }
        }

        $shrink_f = $w / $use_w;

        // ================================================================

        $this->pages[$this->page] .= '___BEFORE_BORDERS___';
        $block_s = $this->PrintPageBackgrounds(); // Save to print later inside clipping path
        $this->pageBackgrounds = [];

        // ================================================================

        if ($rotate == 90 || $rotate == -90) { // mPDF 6
            $prerotw = $bbox_bl + $bbox_pl + $inner_w + $bbox_pr + $bbox_br;
            $preroth = $bbox_bt + $bbox_pt + $inner_h + $bbox_pb + $bbox_bb;
            $rot_start = " q\n";
            if ($rotate == 90) {
                if ($rot_rpos !== false) {
                    $adjw = $prerotw;
                } // width before rotation
                else {
                    $adjw = $preroth;
                } // height before rotation
                if ($rot_bpos !== false) {
                    $adjh = -$prerotw + $preroth;
                } else {
                    $adjh = 0;
                }
            } else {
                if ($rot_rpos !== false) {
                    $adjw = $prerotw - $preroth;
                } else {
                    $adjw = 0;
                }
                if ($rot_bpos !== false) {
                    $adjh = $preroth;
                } // height before rotation
                else {
                    $adjh = $prerotw;
                } // width before rotation
            }
            $rot_start .= $this->transformTranslate($adjw, $adjh, true) . "\n";
            $rot_start .= $this->transformRotate($rotate, $bbox_x, $bbox_y, true) . "\n";
            $rot_end = " Q\n";
        } elseif ($rotate == 180) { // mPDF 6
            $rot_start = " q\n";
            $rot_start .= $this->transformTranslate($bbox_bl + $bbox_pl + $inner_w + $bbox_pr + $bbox_br, $bbox_bt + $bbox_pt + $inner_h + $bbox_pb + $bbox_bb, true) . "\n";
            $rot_start .= $this->transformRotate(180, $bbox_x, $bbox_y, true) . "\n";
            $rot_end = " Q\n";
        } else {
            $rot_start = '';
            $rot_end = '';
        }

        // ================================================================
        if (!empty($bounding)) {
            // WHEN HEIGHT // BOTTOM EDGE IS KNOWN and $this->y is set to the bottom
            // Re-instate saved $this->blk[1]
            $this->blk[1] = $saved_block1;

            // These are only needed when painting border/background
            $this->blk[1]['width'] = $bbox_w = $cont_w - $bbox_left - $bbox_ml - $bbox_mr - $bbox_right;
            $this->blk[1]['x0'] = $bbox_x;
            $this->blk[1]['y0'] = $bbox_y;
            $this->blk[1]['startpage'] = $this->page;
            $this->blk[1]['y1'] = $bbox_y + $bbox_bt + $bbox_pt + $inner_h + $bbox_pb + $bbox_bb;
            $this->_out($rot_start);
            $this->PaintDivBB('', 0, 1); // Prints borders and sets backgrounds in $this->pageBackgrounds
            $this->_out($rot_end);
        }

        $s = $this->PrintPageBackgrounds();
        $s = $rot_start . $s . $rot_end;
        $this->pages[$this->page] = preg_replace('/___BEFORE_BORDERS___/', "\n" . $s . "\n", $this->pages[$this->page]);
        $this->pageBackgrounds = [];

        $this->_out($rot_start);

        // Clipping Output
        if ($overflow == 'hidden') {
            // Bounding rectangle to clip
            $clip_y1 = $this->y;
            if (!empty($bounding) && ($this->y + $bbox_pb + $bbox_bb) > ($bbox_y + $bbox_bt + $bbox_pt + $inner_h + $bbox_pb + $bbox_bb )) {
                $clip_y1 = ($bbox_y + $bbox_bt + $bbox_pt + $inner_h + $bbox_pb + $bbox_bb ) - ($bbox_pb + $bbox_bb);
            }
            // $op = 'W* n';    // Clipping
            $op = 'W n'; // Clipping alternative mode
            $this->_out("q");
            $ch = $clip_y1 - $y;
            $this->_out(sprintf('%.3F %.3F %.3F %.3F re %s', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$ch * Mpdf::SCALE, $op));
            if (!empty($block_s)) {
                $tmp = "q\n" . sprintf('%.3F %.3F %.3F %.3F re %s', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$ch * Mpdf::SCALE, $op);
                $tmp .= "\n" . $block_s . "\nQ";
                $block_s = $tmp;
            }
        }


        if (!empty($block_s)) {
            if ($shrink_f != 1) { // i.e. autofit has resized the box
                $tmp = "q\n" . $this->transformScale(($shrink_f * 100), ($shrink_f * 100), $x, $y, true);
                $tmp .= "\n" . $block_s . "\nQ";
                $block_s = $tmp;
            }
            $this->_out($block_s);
        }



        if ($shrink_f != 1) { // i.e. autofit has resized the box
            $this->StartTransform();
            $this->transformScale(($shrink_f * 100), ($shrink_f * 100), $x, $y);
        }

        $this->_out($this->headerbuffer);

        if ($shrink_f != 1) { // i.e. autofit has resized the box
            $this->StopTransform();
        }

        if ($overflow == 'hidden') {
            // End clipping
            $this->_out("Q");
        }

        $this->_out($rot_end);


        // Page Links
        foreach ($this->HTMLheaderPageLinks as $lk) {
            if ($rotate) {
                $tmp = $lk[2]; // Switch h - w
                $lk[2] = $lk[3];
                $lk[3] = $tmp;

                $lx1 = (($lk[0] / Mpdf::SCALE));
                $ly1 = (($this->h - ($lk[1] / Mpdf::SCALE)));
                if ($rotate == 90) {
                    $adjx = -($lx1 - $bbox_x) + ($preroth - ($ly1 - $bbox_y));
                    $adjy = -($ly1 - $bbox_y) + ($lx1 - $bbox_x);
                    $lk[2] = -$lk[2];
                } elseif ($rotate == -90) {
                    $adjx = -($lx1 - $bbox_x) + ($ly1 - $bbox_y);
                    $adjy = -($ly1 - $bbox_y) - ($lx1 - $bbox_x) + $prerotw;
                    $lk[3] = -$lk[3];
                }
                if ($rot_rpos !== false) {
                    $adjx += $prerotw - $preroth;
                }
                if ($rot_bpos !== false) {
                    $adjy += $preroth - $prerotw;
                }
                $lx1 += $adjx;
                $ly1 += $adjy;

                $lk[0] = $lx1 * Mpdf::SCALE;
                $lk[1] = ($this->h - $ly1) * Mpdf::SCALE;
            }
            if ($shrink_f != 1) {  // i.e. autofit has resized the box
                $lx1 = (($lk[0] / Mpdf::SCALE) - $x);
                $lx2 = $x + ($lx1 * $shrink_f);
                $lk[0] = $lx2 * Mpdf::SCALE;
                $ly1 = (($this->h - ($lk[1] / Mpdf::SCALE)) - $y);
                $ly2 = $y + ($ly1 * $shrink_f);
                $lk[1] = ($this->h - $ly2) * Mpdf::SCALE;
                $lk[2] *= $shrink_f; // width
                $lk[3] *= $shrink_f; // height
            }
            $this->PageLinks[$this->page][] = $lk;
        }

        foreach ($this->HTMLheaderPageForms as $n => $f) {
            if ($shrink_f != 1) {  // i.e. autofit has resized the box
                $f['x'] = $x + (($f['x'] - $x) * $shrink_f);
                $f['y'] = $y + (($f['y'] - $y) * $shrink_f);
                $f['w'] *= $shrink_f;
                $f['h'] *= $shrink_f;
                $f['style']['fontsize'] *= $shrink_f;
            }
            $this->form->forms[$f['n']] = $f;
        }
        // Page Annotations
        foreach ($this->HTMLheaderPageAnnots as $lk) {
            if ($rotate) {
                if ($rotate == 90) {
                    $adjx = -($lk['x'] - $bbox_x) + ($preroth - ($lk['y'] - $bbox_y));
                    $adjy = -($lk['y'] - $bbox_y) + ($lk['x'] - $bbox_x);
                } elseif ($rotate == -90) {
                    $adjx = -($lk['x'] - $bbox_x) + ($lk['y'] - $bbox_y);
                    $adjy = -($lk['y'] - $bbox_y) - ($lk['x'] - $bbox_x) + $prerotw;
                }
                if ($rot_rpos !== false) {
                    $adjx += $prerotw - $preroth;
                }
                if ($rot_bpos !== false) {
                    $adjy += $preroth - $prerotw;
                }
                $lk['x'] += $adjx;
                $lk['y'] += $adjy;
            }
            if ($shrink_f != 1) {  // i.e. autofit has resized the box
                $lk['x'] = $x + (($lk['x'] - $x) * $shrink_f);
                $lk['y'] = $y + (($lk['y'] - $y) * $shrink_f);
            }
            $this->PageAnnots[$this->page][] = $lk;
        }

        // Restore
        $this->headerbuffer = '';
        $this->HTMLheaderPageLinks = [];
        $this->HTMLheaderPageAnnots = [];
        $this->HTMLheaderPageForms = [];
        $this->pageBackgrounds = $save_bgs;
        $this->writingHTMLheader = false;

        $this->writingHTMLfooter = false;
        $this->fullImageHeight = false;
        $this->ResetMargins();
        $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
        $this->SetXY($save_x, $save_y);
        $this->title2annots = $save_annots; // *ANNOTATIONS*
        $this->InFooter = false; // turns back on autopagebreaks
        $this->pageoutput[$this->page] = [];
        $this->pageoutput[$this->page]['Font'] = '';
        /* -- COLUMNS -- */
        if ($save_cols) {
            $this->SetColumns($save_nbcol, $this->colvAlign, $this->ColGap);
        }
        /* -- END COLUMNS -- */
    }

    /* -- END CSS-POSITION -- */

    public function initialiseBlock(&$blk)
    {
        $blk['margin_top'] = 0;
        $blk['margin_left'] = 0;
        $blk['margin_bottom'] = 0;
        $blk['margin_right'] = 0;
        $blk['padding_top'] = 0;
        $blk['padding_left'] = 0;
        $blk['padding_bottom'] = 0;
        $blk['padding_right'] = 0;
        $blk['border_top']['w'] = 0;
        $blk['border_left']['w'] = 0;
        $blk['border_bottom']['w'] = 0;
        $blk['border_right']['w'] = 0;
        $blk['direction'] = 'ltr';
        $blk['hide'] = false;
        $blk['outer_left_margin'] = 0;
        $blk['outer_right_margin'] = 0;
        $blk['cascadeCSS'] = [];
        $blk['block-align'] = false;
        $blk['bgcolor'] = false;
        $blk['page_break_after_avoid'] = false;
        $blk['keep_block_together'] = false;
        $blk['float'] = false;
        $blk['line_height'] = '';
        $blk['margin_collapse'] = false;
    }

    public function border_details($bd)
    {
        $prop = preg_split('/\s+/', trim($bd));

        if (isset($this->blk[$this->blklvl]['inner_width'])) {
            $refw = $this->blk[$this->blklvl]['inner_width'];
        } elseif (isset($this->blk[$this->blklvl - 1]['inner_width'])) {
            $refw = $this->blk[$this->blklvl - 1]['inner_width'];
        } else {
            $refw = $this->w;
        }
        if (count($prop) == 1) {
            $bsize = $this->sizeConverter->convert($prop[0], $refw, $this->FontSize, false);
            if ($bsize > 0) {
                return ['s' => 1, 'w' => $bsize, 'c' => $this->colorConverter->convert(0, $this->PDFAXwarnings), 'style' => 'solid'];
            } else {
                return ['w' => 0, 's' => 0];
            }
        } elseif (count($prop) == 2) {
            // 1px solid
            if (in_array($prop[1], $this->borderstyles) || $prop[1] == 'none' || $prop[1] == 'hidden') {
                $prop[2] = '';
            } // solid #000000
            elseif (in_array($prop[0], $this->borderstyles) || $prop[0] == 'none' || $prop[0] == 'hidden') {
                $prop[0] = '';
                $prop[1] = $prop[0];
                $prop[2] = $prop[1];
            } // 1px #000000
            else {
                $prop[1] = '';
                $prop[2] = $prop[1];
            }
        } elseif (count($prop) == 3) {
            // Change #000000 1px solid to 1px solid #000000 (proper)
            if (substr($prop[0], 0, 1) == '#') {
                $tmp = $prop[0];
                $prop[0] = $prop[1];
                $prop[1] = $prop[2];
                $prop[2] = $tmp;
            } // Change solid #000000 1px to 1px solid #000000 (proper)
            elseif (substr($prop[0], 1, 1) == '#') {
                $tmp = $prop[1];
                $prop[0] = $prop[2];
                $prop[1] = $prop[0];
                $prop[2] = $tmp;
            } // Change solid 1px #000000 to 1px solid #000000 (proper)
            elseif (in_array($prop[0], $this->borderstyles) || $prop[0] == 'none' || $prop[0] == 'hidden') {
                $tmp = $prop[0];
                $prop[0] = $prop[1];
                $prop[1] = $tmp;
            }
        } else {
            return [];
        }
        // Size
        $bsize = $this->sizeConverter->convert($prop[0], $refw, $this->FontSize, false);
        // color
        $coul = $this->colorConverter->convert($prop[2], $this->PDFAXwarnings); // returns array
        // Style
        $prop[1] = strtolower($prop[1]);
        if (in_array($prop[1], $this->borderstyles) && $bsize > 0) {
            $on = 1;
        } elseif ($prop[1] == 'hidden') {
            $on = 1;
            $bsize = 0;
            $coul = '';
        } elseif ($prop[1] == 'none') {
            $on = 0;
            $bsize = 0;
            $coul = '';
        } else {
            $on = 0;
            $bsize = 0;
            $coul = '';
            $prop[1] = '';
        }
        return ['s' => $on, 'w' => $bsize, 'c' => $coul, 'style' => $prop[1], 'dom' => 0];
    }

    /* -- END HTML-CSS -- */

    /* -- HTML-CSS -- */
    /* -- CSS-PAGE -- */

    public function SetPagedMediaCSS($name, $first, $oddEven)
    {
        if ($oddEven == 'E') {
            if ($this->directionality == 'rtl') {
                $side = 'R';
            } else {
                $side = 'L';
            }
        } else {
            if ($this->directionality == 'rtl') {
                $side = 'L';
            } else {
                $side = 'R';
            }
        }
        $name = strtoupper($name);
        $p = [];
        $p['SIZE'] = 'AUTO';

        // Uses mPDF original margins as default
        $p['MARGIN-RIGHT'] = strval($this->orig_rMargin) . 'mm';
        $p['MARGIN-LEFT'] = strval($this->orig_lMargin) . 'mm';
        $p['MARGIN-TOP'] = strval($this->orig_tMargin) . 'mm';
        $p['MARGIN-BOTTOM'] = strval($this->orig_bMargin) . 'mm';
        $p['MARGIN-HEADER'] = strval($this->orig_hMargin) . 'mm';
        $p['MARGIN-FOOTER'] = strval($this->orig_fMargin) . 'mm';

        // Basic page + selector
        if (isset($this->cssManager->CSS['@PAGE'])) {
            $zp = $this->cssManager->CSS['@PAGE'];
        } else {
            $zp = [];
        }
        if (is_array($zp) && !empty($zp)) {
            $p = array_merge($p, $zp);
        }

        if (isset($p['EVEN-HEADER-NAME']) && $oddEven == 'E') {
            $p['HEADER'] = $p['EVEN-HEADER-NAME'];
            unset($p['EVEN-HEADER-NAME']);
        }
        if (isset($p['ODD-HEADER-NAME']) && $oddEven != 'E') {
            $p['HEADER'] = $p['ODD-HEADER-NAME'];
            unset($p['ODD-HEADER-NAME']);
        }
        if (isset($p['EVEN-FOOTER-NAME']) && $oddEven == 'E') {
            $p['FOOTER'] = $p['EVEN-FOOTER-NAME'];
            unset($p['EVEN-FOOTER-NAME']);
        }
        if (isset($p['ODD-FOOTER-NAME']) && $oddEven != 'E') {
            $p['FOOTER'] = $p['ODD-FOOTER-NAME'];
            unset($p['ODD-FOOTER-NAME']);
        }

        // If right/Odd page
        if (isset($this->cssManager->CSS['@PAGE>>PSEUDO>>RIGHT']) && $side == 'R') {
            $zp = $this->cssManager->CSS['@PAGE>>PSEUDO>>RIGHT'];
        } else {
            $zp = [];
        }
        if (isset($zp['SIZE'])) {
            unset($zp['SIZE']);
        }
        if (isset($zp['SHEET-SIZE'])) {
            unset($zp['SHEET-SIZE']);
        }
        // Disallow margin-left or -right on :LEFT or :RIGHT
        if (isset($zp['MARGIN-LEFT'])) {
            unset($zp['MARGIN-LEFT']);
        }
        if (isset($zp['MARGIN-RIGHT'])) {
            unset($zp['MARGIN-RIGHT']);
        }
        if (is_array($zp) && !empty($zp)) {
            $p = array_merge($p, $zp);
        }

        // If left/Even page
        if (isset($this->cssManager->CSS['@PAGE>>PSEUDO>>LEFT']) && $side == 'L') {
            $zp = $this->cssManager->CSS['@PAGE>>PSEUDO>>LEFT'];
        } else {
            $zp = [];
        }
        if (isset($zp['SIZE'])) {
            unset($zp['SIZE']);
        }
        if (isset($zp['SHEET-SIZE'])) {
            unset($zp['SHEET-SIZE']);
        }
        // Disallow margin-left or -right on :LEFT or :RIGHT
        if (isset($zp['MARGIN-LEFT'])) {
            unset($zp['MARGIN-LEFT']);
        }
        if (isset($zp['MARGIN-RIGHT'])) {
            unset($zp['MARGIN-RIGHT']);
        }
        if (is_array($zp) && !empty($zp)) {
            $p = array_merge($p, $zp);
        }

        // If first page
        if (isset($this->cssManager->CSS['@PAGE>>PSEUDO>>FIRST']) && $first) {
            $zp = $this->cssManager->CSS['@PAGE>>PSEUDO>>FIRST'];
        } else {
            $zp = [];
        }
        if (isset($zp['SIZE'])) {
            unset($zp['SIZE']);
        }
        if (isset($zp['SHEET-SIZE'])) {
            unset($zp['SHEET-SIZE']);
        }
        // Disallow margin-left or -right on :FIRST // mPDF 5.7.3
        if (isset($zp['MARGIN-LEFT'])) {
            unset($zp['MARGIN-LEFT']);
        }
        if (isset($zp['MARGIN-RIGHT'])) {
            unset($zp['MARGIN-RIGHT']);
        }
        if (is_array($zp) && !empty($zp)) {
            $p = array_merge($p, $zp);
        }

        // If named page
        if ($name) {
            if (isset($this->cssManager->CSS['@PAGE>>NAMED>>' . $name])) {
                $zp = $this->cssManager->CSS['@PAGE>>NAMED>>' . $name];
            } else {
                $zp = [];
            }
            if (is_array($zp) && !empty($zp)) {
                $p = array_merge($p, $zp);
            }

            if (isset($p['EVEN-HEADER-NAME']) && $oddEven == 'E') {
                $p['HEADER'] = $p['EVEN-HEADER-NAME'];
                unset($p['EVEN-HEADER-NAME']);
            }
            if (isset($p['ODD-HEADER-NAME']) && $oddEven != 'E') {
                $p['HEADER'] = $p['ODD-HEADER-NAME'];
                unset($p['ODD-HEADER-NAME']);
            }
            if (isset($p['EVEN-FOOTER-NAME']) && $oddEven == 'E') {
                $p['FOOTER'] = $p['EVEN-FOOTER-NAME'];
                unset($p['EVEN-FOOTER-NAME']);
            }
            if (isset($p['ODD-FOOTER-NAME']) && $oddEven != 'E') {
                $p['FOOTER'] = $p['ODD-FOOTER-NAME'];
                unset($p['ODD-FOOTER-NAME']);
            }

            // If named right/Odd page
            if (isset($this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>RIGHT']) && $side == 'R') {
                $zp = $this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>RIGHT'];
            } else {
                $zp = [];
            }
            if (isset($zp['SIZE'])) {
                unset($zp['SIZE']);
            }
            if (isset($zp['SHEET-SIZE'])) {
                unset($zp['SHEET-SIZE']);
            }
            // Disallow margin-left or -right on :LEFT or :RIGHT
            if (isset($zp['MARGIN-LEFT'])) {
                unset($zp['MARGIN-LEFT']);
            }
            if (isset($zp['MARGIN-RIGHT'])) {
                unset($zp['MARGIN-RIGHT']);
            }
            if (is_array($zp) && !empty($zp)) {
                $p = array_merge($p, $zp);
            }

            // If named left/Even page
            if (isset($this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>LEFT']) && $side == 'L') {
                $zp = $this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>LEFT'];
            } else {
                $zp = [];
            }
            if (isset($zp['SIZE'])) {
                unset($zp['SIZE']);
            }
            if (isset($zp['SHEET-SIZE'])) {
                unset($zp['SHEET-SIZE']);
            }
            // Disallow margin-left or -right on :LEFT or :RIGHT
            if (isset($zp['MARGIN-LEFT'])) {
                unset($zp['MARGIN-LEFT']);
            }
            if (isset($zp['MARGIN-RIGHT'])) {
                unset($zp['MARGIN-RIGHT']);
            }
            if (is_array($zp) && !empty($zp)) {
                $p = array_merge($p, $zp);
            }

            // If named first page
            if (isset($this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>FIRST']) && $first) {
                $zp = $this->cssManager->CSS['@PAGE>>NAMED>>' . $name . '>>PSEUDO>>FIRST'];
            } else {
                $zp = [];
            }
            if (isset($zp['SIZE'])) {
                unset($zp['SIZE']);
            }
            if (isset($zp['SHEET-SIZE'])) {
                unset($zp['SHEET-SIZE']);
            }
            // Disallow margin-left or -right on :FIRST // mPDF 5.7.3
            if (isset($zp['MARGIN-LEFT'])) {
                unset($zp['MARGIN-LEFT']);
            }
            if (isset($zp['MARGIN-RIGHT'])) {
                unset($zp['MARGIN-RIGHT']);
            }
            if (is_array($zp) && !empty($zp)) {
                $p = array_merge($p, $zp);
            }
        }

        $orientation = $mgl = $mgr = $mgt = $mgb = $mgh = $mgf = '';
        $header = $footer = '';
        $resetpagenum = $pagenumstyle = $suppress = '';
        $marks = '';
        $bg = [];

        $newformat = '';


        if (isset($p['SHEET-SIZE']) && is_array($p['SHEET-SIZE'])) {
            $newformat = $p['SHEET-SIZE'];
            if ($newformat[0] > $newformat[1]) { // landscape
                $newformat = array_reverse($newformat);
                $p['ORIENTATION'] = 'L';
            } else {
                $p['ORIENTATION'] = 'P';
            }
            $this->_setPageSize($newformat, $p['ORIENTATION']);
        }

        if (isset($p['SIZE']) && is_array($p['SIZE']) && !$newformat) {
            if ($p['SIZE']['W'] > $p['SIZE']['H']) {
                $p['ORIENTATION'] = 'L';
            } else {
                $p['ORIENTATION'] = 'P';
            }
        }
        if (is_array($p['SIZE'])) {
            if ($p['SIZE']['W'] > $this->fw) {
                $p['SIZE']['W'] = $this->fw;
            } // mPD 4.2 use fw not fPt
            if ($p['SIZE']['H'] > $this->fh) {
                $p['SIZE']['H'] = $this->fh;
            }
            if (($p['ORIENTATION'] == $this->DefOrientation && !$newformat) || ($newformat && $p['ORIENTATION'] == 'P')) {
                $outer_width_LR = ($this->fw - $p['SIZE']['W']) / 2;
                $outer_width_TB = ($this->fh - $p['SIZE']['H']) / 2;
            } else {
                $outer_width_LR = ($this->fh - $p['SIZE']['W']) / 2;
                $outer_width_TB = ($this->fw - $p['SIZE']['H']) / 2;
            }
            $pgw = $p['SIZE']['W'];
            $pgh = $p['SIZE']['H'];
        } else { // AUTO LANDSCAPE PORTRAIT
            $outer_width_LR = 0;
            $outer_width_TB = 0;
            if (!$newformat) {
                if (strtoupper($p['SIZE']) == 'AUTO') {
                    $p['ORIENTATION'] = $this->DefOrientation;
                } elseif (strtoupper($p['SIZE']) == 'LANDSCAPE') {
                    $p['ORIENTATION'] = 'L';
                } else {
                    $p['ORIENTATION'] = 'P';
                }
            }
            if (($p['ORIENTATION'] == $this->DefOrientation && !$newformat) || ($newformat && $p['ORIENTATION'] == 'P')) {
                $pgw = $this->fw;
                $pgh = $this->fh;
            } else {
                $pgw = $this->fh;
                $pgh = $this->fw;
            }
        }

        if (isset($p['HEADER']) && $p['HEADER']) {
            $header = $p['HEADER'];
        }
        if (isset($p['FOOTER']) && $p['FOOTER']) {
            $footer = $p['FOOTER'];
        }
        if (isset($p['RESETPAGENUM']) && $p['RESETPAGENUM']) {
            $resetpagenum = $p['RESETPAGENUM'];
        }
        if (isset($p['PAGENUMSTYLE']) && $p['PAGENUMSTYLE']) {
            $pagenumstyle = $p['PAGENUMSTYLE'];
        }
        if (isset($p['SUPPRESS']) && $p['SUPPRESS']) {
            $suppress = $p['SUPPRESS'];
        }

        if (isset($p['MARKS'])) {
            if (preg_match('/cross/i', $p['MARKS']) && preg_match('/crop/i', $p['MARKS'])) {
                $marks = 'CROPCROSS';
            } elseif (strtoupper($p['MARKS']) == 'CROP') {
                $marks = 'CROP';
            } elseif (strtoupper($p['MARKS']) == 'CROSS') {
                $marks = 'CROSS';
            }
        }

        if (isset($p['BACKGROUND-COLOR']) && $p['BACKGROUND-COLOR']) {
            $bg['BACKGROUND-COLOR'] = $p['BACKGROUND-COLOR'];
        }
        /* -- BACKGROUNDS -- */
        if (isset($p['BACKGROUND-GRADIENT']) && $p['BACKGROUND-GRADIENT']) {
            $bg['BACKGROUND-GRADIENT'] = $p['BACKGROUND-GRADIENT'];
        }
        if (isset($p['BACKGROUND-IMAGE']) && $p['BACKGROUND-IMAGE']) {
            $bg['BACKGROUND-IMAGE'] = $p['BACKGROUND-IMAGE'];
        }
        if (isset($p['BACKGROUND-REPEAT']) && $p['BACKGROUND-REPEAT']) {
            $bg['BACKGROUND-REPEAT'] = $p['BACKGROUND-REPEAT'];
        }
        if (isset($p['BACKGROUND-POSITION']) && $p['BACKGROUND-POSITION']) {
            $bg['BACKGROUND-POSITION'] = $p['BACKGROUND-POSITION'];
        }
        if (isset($p['BACKGROUND-IMAGE-RESIZE']) && $p['BACKGROUND-IMAGE-RESIZE']) {
            $bg['BACKGROUND-IMAGE-RESIZE'] = $p['BACKGROUND-IMAGE-RESIZE'];
        }
        if (isset($p['BACKGROUND-IMAGE-OPACITY'])) {
            $bg['BACKGROUND-IMAGE-OPACITY'] = $p['BACKGROUND-IMAGE-OPACITY'];
        }
        /* -- END BACKGROUNDS -- */

        if (isset($p['MARGIN-LEFT'])) {
            $mgl = $this->sizeConverter->convert($p['MARGIN-LEFT'], $pgw) + $outer_width_LR;
        }
        if (isset($p['MARGIN-RIGHT'])) {
            $mgr = $this->sizeConverter->convert($p['MARGIN-RIGHT'], $pgw) + $outer_width_LR;
        }
        if (isset($p['MARGIN-BOTTOM'])) {
            $mgb = $this->sizeConverter->convert($p['MARGIN-BOTTOM'], $pgh) + $outer_width_TB;
        }
        if (isset($p['MARGIN-TOP'])) {
            $mgt = $this->sizeConverter->convert($p['MARGIN-TOP'], $pgh) + $outer_width_TB;
        }
        if (isset($p['MARGIN-HEADER'])) {
            $mgh = $this->sizeConverter->convert($p['MARGIN-HEADER'], $pgh) + $outer_width_TB;
        }
        if (isset($p['MARGIN-FOOTER'])) {
            $mgf = $this->sizeConverter->convert($p['MARGIN-FOOTER'], $pgh) + $outer_width_TB;
        }

        if (isset($p['ORIENTATION']) && $p['ORIENTATION']) {
            $orientation = $p['ORIENTATION'];
        }
        $this->page_box['outer_width_LR'] = $outer_width_LR; // Used in MARKS:crop etc.
        $this->page_box['outer_width_TB'] = $outer_width_TB;

        return [$orientation, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $header, $footer, $bg, $resetpagenum, $pagenumstyle, $suppress, $marks, $newformat];
    }

    /* -- END CSS-PAGE -- */



    /* -- CSS-FLOAT -- */

    // Added mPDF 3.0 Float DIV - CLEAR
    public function ClearFloats($clear, $blklvl = 0)
    {
        list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($blklvl, true);
        $end = $currpos = ($this->page * 1000 + $this->y);
        if ($clear == 'BOTH' && ($l_exists || $r_exists)) {
            $this->pageoutput[$this->page] = [];
            $end = max($l_max, $r_max, $currpos);
        } elseif ($clear == 'RIGHT' && $r_exists) {
            $this->pageoutput[$this->page] = [];
            $end = max($r_max, $currpos);
        } elseif ($clear == 'LEFT' && $l_exists) {
            $this->pageoutput[$this->page] = [];
            $end = max($l_max, $currpos);
        } else {
            return;
        }
        $old_page = $this->page;
        $new_page = intval($end / 1000);
        if ($old_page != $new_page) {
            $s = $this->PrintPageBackgrounds();
            // Writes after the marker so not overwritten later by page background etc.
            $this->pages[$this->page] = preg_replace('/(___BACKGROUND___PATTERNS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->pages[$this->page]);
            $this->pageBackgrounds = [];
            $this->page = $new_page;
        }
        $this->ResetMargins();
        $this->pageoutput[$this->page] = [];
        $this->y = (($end * 1000) % 1000000) / 1000; // mod changes operands to integers before processing
    }

    // Added mPDF 3.0 Float DIV
    public function GetFloatDivInfo($blklvl = 0, $clear = false)
    {
        // If blklvl specified, only returns floats at that level - for ClearFloats
        $l_exists = false;
        $r_exists = false;
        $l_max = 0;
        $r_max = 0;
        $l_width = 0;
        $r_width = 0;
        if (count($this->floatDivs)) {
            $currpos = ($this->page * 1000 + $this->y);
            foreach ($this->floatDivs as $f) {
                if (($clear && $f['blockContext'] == $this->blk[$blklvl]['blockContext']) || (!$clear && $currpos >= $f['startpos'] && $currpos < ($f['endpos'] - 0.001) && $f['blklvl'] > $blklvl && $f['blockContext'] == $this->blk[$blklvl]['blockContext'])) {
                    if ($f['side'] == 'L') {
                        $l_exists = true;
                        $l_max = max($l_max, $f['endpos']);
                        $l_width = max($l_width, $f['w']);
                    }
                    if ($f['side'] == 'R') {
                        $r_exists = true;
                        $r_max = max($r_max, $f['endpos']);
                        $r_width = max($r_width, $f['w']);
                    }
                }
            }
        }
        return [$l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width];
    }

    /* -- END CSS-FLOAT -- */

    /* -- TABLES -- */

    // This function determines the shrink factor when resizing tables
    // val is the table_height / page_height_available
    // returns a scaling factor used as $shrin_k to resize the table
    // Overcompensating will be quicker but may unnecessarily shrink table too much
    // Undercompensating means it will reiterate more times (taking more processing time)
    public function tbsqrt($val, $iteration = 3)
    {
        $k = 4; // Alters number of iterations until it returns $val itself - Must be > 2
        // Probably best guess and most accurate
        if ($iteration == 1) {
            return sqrt($val);
        }
        // Faster than using sqrt (because it won't undercompensate), and gives reasonable results
        // return 1+(($val-1)/2);
        $x = 2 - (($iteration - 2) / ($k - 2));
        if ($x == 0) {
            $ret = $val + 0.00001;
        } elseif ($x < 0) {
            $ret = 1 + ( pow(2, ($iteration - 2 - $k)) / 1000 );
        } else {
            $ret = 1 + (($val - 1) / $x);
        }
        return $ret;
    }

    /* -- END TABLES -- */

    public function printbuffer($arrayaux, $blockstate = 0, $is_table = false, $table_draft = false, $cell_dir = '')
    {
        // $blockstate = 0; // NO margins/padding
        // $blockstate = 1; // Top margins/padding only
        // $blockstate = 2; // Bottom margins/padding only
        // $blockstate = 3; // Top & bottom margins/padding
        $this->spanbgcolorarray = '';
        $this->spanbgcolor = false;
        $this->spanborder = false;
        $this->spanborddet = [];
        $paint_ht_corr = 0;
        /* -- CSS-FLOAT -- */
        if (count($this->floatDivs)) {
            list($l_exists, $r_exists, $l_max, $r_max, $l_width, $r_width) = $this->GetFloatDivInfo($this->blklvl);
            if (($this->blk[$this->blklvl]['inner_width'] - $l_width - $r_width) < (2 * $this->GetCharWidth('W', false))) {
                // Too narrow to fit - try to move down past L or R float
                if ($l_max < $r_max && ($this->blk[$this->blklvl]['inner_width'] - $r_width) > (2 * $this->GetCharWidth('W', false))) {
                    $this->ClearFloats('LEFT', $this->blklvl);
                } elseif ($r_max < $l_max && ($this->blk[$this->blklvl]['inner_width'] - $l_width) > (2 * $this->GetCharWidth('W', false))) {
                    $this->ClearFloats('RIGHT', $this->blklvl);
                } else {
                    $this->ClearFloats('BOTH', $this->blklvl);
                }
            }
        }
        /* -- END CSS-FLOAT -- */
        $bak_y = $this->y;
        $bak_x = $this->x;
        $align = '';
        if (!$is_table) {
            if (isset($this->blk[$this->blklvl]['align']) && $this->blk[$this->blklvl]['align']) {
                $align = $this->blk[$this->blklvl]['align'];
            }
            // Block-align is set by e.g. <.. align="center"> Takes priority for this block but not inherited
            if (isset($this->blk[$this->blklvl]['block-align']) && $this->blk[$this->blklvl]['block-align']) {
                $align = $this->blk[$this->blklvl]['block-align'];
            }
            if (isset($this->blk[$this->blklvl]['direction'])) {
                $blockdir = $this->blk[$this->blklvl]['direction'];
            } else {
                $blockdir = "";
            }
            $this->divwidth = $this->blk[$this->blklvl]['width'];
        } else {
            $align = $this->cellTextAlign;
            $blockdir = $cell_dir;
        }
        $oldpage = $this->page;

        // ADDED for Out of Block now done as Flowing Block
        if ($this->divwidth == 0) {
            $this->divwidth = $this->pgwidth;
        }

        if (!$is_table) {
            $this->SetLineHeight($this->FontSizePt, $this->blk[$this->blklvl]['line_height']);
        }
        $this->divheight = $this->lineheight;
        $old_height = $this->divheight;

        // As a failsafe - if font has been set but not output to page
        if (!$table_draft) {
            $this->SetFont($this->default_font, '', $this->default_font_size, true, true); // force output to page
        }

        $this->newFlowingBlock($this->divwidth, $this->divheight, $align, $is_table, $blockstate, true, $blockdir, $table_draft);

        $array_size = count($arrayaux);

        // Added - Otherwise <div><div><p> did not output top margins/padding for 1st/2nd div
        if ($array_size == 0) {
            $this->finishFlowingBlock(true);
        } // true = END of flowing block
        // mPDF 6
        // ALL the chunks of textbuffer need to have at least basic OTLdata set
        // First make sure each element/chunk has the OTLdata for Bidi set.
        for ($i = 0; $i < $array_size; $i++) {
            if (empty($arrayaux[$i][18])) {
                if (substr($arrayaux[$i][0], 0, 3) == "\xbb\xa4\xac") { // object identifier has been identified!
                    $unicode = [0xFFFC]; // Object replacement character
                } else {
                    $unicode = $this->UTF8StringToArray($arrayaux[$i][0], false);
                }
                $is_strong = false;
                $this->getBasicOTLdata($arrayaux[$i][18], $unicode, $is_strong);
            }
            // Gets messed up if try and use core fonts inside a paragraph of text which needs to be BiDi re-ordered or OTLdata set
            if (($blockdir == 'rtl' || $this->biDirectional) && isset($arrayaux[$i][4]) && in_array($arrayaux[$i][4], ['ccourier', 'ctimes', 'chelvetica', 'csymbol', 'czapfdingbats'])) {
                throw new \Mpdf\MpdfException("You cannot use core fonts in a document which contains RTL text.");
            }
        }
        // mPDF 6
        // Process bidirectional text ready for bidi-re-ordering (which is done after line-breaks are established in WriteFlowingBlock etc.)
        if (($blockdir == 'rtl' || $this->biDirectional) && !$table_draft) {
            if (empty($this->otl)) {
                $this->otl = new Otl($this, $this->fontCache);
            }
            $this->otl->bidiPrepare($arrayaux, $blockdir);
            $array_size = count($arrayaux);
        }


        // Remove empty items // mPDF 6
        for ($i = $array_size - 1; $i > 0; $i--) {
            if (empty($arrayaux[$i][0]) && (isset($arrayaux[$i][16]) && $arrayaux[$i][16] !== '0') && empty($arrayaux[$i][7])) {
                unset($arrayaux[$i]);
            }
        }

        // Correct adjoining borders for inline elements
        if (isset($arrayaux[0][16])) {
            $lastspanborder = $arrayaux[0][16];
        } else {
            $lastspanborder = false;
        }
        for ($i = 1; $i < $array_size; $i++) {
            if (isset($arrayaux[$i][16]) && $arrayaux[$i][16] == $lastspanborder &&
                ((!isset($arrayaux[$i][9]['bord-decoration']) && !isset($arrayaux[$i - 1][9]['bord-decoration'])) ||
                (isset($arrayaux[$i][9]['bord-decoration']) && isset($arrayaux[$i - 1][9]['bord-decoration']) && $arrayaux[$i][9]['bord-decoration'] == $arrayaux[$i - 1][9]['bord-decoration'])
                )
            ) {
                if (isset($arrayaux[$i][16]['R'])) {
                    $lastspanborder = $arrayaux[$i][16];
                } else {
                    $lastspanborder = false;
                }
                $arrayaux[$i][16]['L']['s'] = 0;
                $arrayaux[$i][16]['L']['w'] = 0;
                $arrayaux[$i - 1][16]['R']['s'] = 0;
                $arrayaux[$i - 1][16]['R']['w'] = 0;
            } else {
                if (isset($arrayaux[$i][16]['R'])) {
                    $lastspanborder = $arrayaux[$i][16];
                } else {
                    $lastspanborder = false;
                }
            }
        }

        for ($i = 0; $i < $array_size; $i++) {
            // COLS
            $oldcolumn = $this->CurrCol;
            $vetor = isset($arrayaux[$i]) ? $arrayaux[$i] : null;
            if ($i == 0 && $vetor[0] != "\n" && ! $this->ispre) {
                $vetor[0] = ltrim($vetor[0]);
                if (!empty($vetor[18])) {
                    $this->otl->trimOTLdata($vetor[18], true, false);
                } // *OTL*
            }

            // FIXED TO ALLOW IT TO SHOW '0'
            if (empty($vetor[0]) && !($vetor[0] === '0') && empty($vetor[7])) { // Ignore empty text and not carrying an internal link
                // Check if it is the last element. If so then finish printing the block
                if ($i == ($array_size - 1)) {
                    $this->finishFlowingBlock(true);
                } // true = END of flowing block
                continue;
            }


            // Activating buffer properties
            if (isset($vetor[11]) && $vetor[11] != '') {   // Font Size
                if ($is_table && $this->shrin_k) {
                    $this->SetFontSize($vetor[11] / $this->shrin_k, false);
                } else {
                    $this->SetFontSize($vetor[11], false);
                }
            }

            if (isset($vetor[17]) && !empty($vetor[17])) { // TextShadow
                $this->textshadow = $vetor[17];
            }
            if (isset($vetor[16]) && !empty($vetor[16])) { // Border
                $this->spanborddet = $vetor[16];
                $this->spanborder = true;
            }

            if (isset($vetor[15])) {   // Word spacing
                $this->wSpacingCSS = $vetor[15];
                if ($this->wSpacingCSS && strtoupper($this->wSpacingCSS) != 'NORMAL') {
                    $this->minwSpacing = $this->sizeConverter->convert($this->wSpacingCSS, $this->FontSize) / $this->shrin_k; // mPDF 5.7.3
                }
            }
            if (isset($vetor[14])) {   // Letter spacing
                $this->lSpacingCSS = $vetor[14];
                if (($this->lSpacingCSS || $this->lSpacingCSS === '0') && strtoupper($this->lSpacingCSS) != 'NORMAL') {
                    $this->fixedlSpacing = $this->sizeConverter->convert($this->lSpacingCSS, $this->FontSize) / $this->shrin_k; // mPDF 5.7.3
                }
            }


            if (isset($vetor[10]) and ! empty($vetor[10])) { // Background color
                $this->spanbgcolorarray = $vetor[10];
                $this->spanbgcolor = true;
            }
            if (isset($vetor[9]) and ! empty($vetor[9])) { // Text parameters - Outline + hyphens
                $this->textparam = $vetor[9];
                $this->SetTextOutline($this->textparam);
                // mPDF 5.7.3  inline text-decoration parameters
                if ($is_table && $this->shrin_k) {
                    if (isset($this->textparam['text-baseline'])) {
                        $this->textparam['text-baseline'] /= $this->shrin_k;
                    }
                    if (isset($this->textparam['decoration-baseline'])) {
                        $this->textparam['decoration-baseline'] /= $this->shrin_k;
                    }
                    if (isset($this->textparam['decoration-fontsize'])) {
                        $this->textparam['decoration-fontsize'] /= $this->shrin_k;
                    }
                }
            }
            if (isset($vetor[8])) {  // mPDF 5.7.1
                $this->textvar = $vetor[8];
            }
            if (isset($vetor[7]) and $vetor[7] != '') { // internal target: <a name="anyvalue">
                $ily = $this->y;
                if ($this->table_rotate) {
                    $this->internallink[$vetor[7]] = ["Y" => $ily, "PAGE" => $this->page, "tbrot" => true];
                } elseif ($this->kwt) {
                    $this->internallink[$vetor[7]] = ["Y" => $ily, "PAGE" => $this->page, "kwt" => true];
                } elseif ($this->ColActive) {
                    $this->internallink[$vetor[7]] = ["Y" => $ily, "PAGE" => $this->page, "col" => $this->CurrCol];
                } elseif (!$this->keep_block_together) {
                    $this->internallink[$vetor[7]] = ["Y" => $ily, "PAGE" => $this->page];
                }
                if (empty($vetor[0])) { // Ignore empty text
                    // Check if it is the last element. If so then finish printing the block
                    if ($i == ($array_size - 1)) {
                        $this->finishFlowingBlock(true);
                    } // true = END of flowing block
                    continue;
                }
            }
            if (isset($vetor[5]) and $vetor[5] != '') {  // Language    // mPDF 6
                $this->currentLang = $vetor[5];
            }
            if (isset($vetor[4]) and $vetor[4] != '') {  // Font Family
                $font = $this->SetFont($vetor[4], $this->FontStyle, 0, false);
            }
            if (!empty($vetor[3])) { // Font Color
                $cor = $vetor[3];
                $this->SetTColor($cor);
            }
            if (isset($vetor[2]) and $vetor[2] != '') { // Bold,Italic styles
                $this->SetStyles($vetor[2]);
            }

            if (isset($vetor[12]) and $vetor[12] != '') { // Requested Bold,Italic
                $this->ReqFontStyle = $vetor[12];
            }
            if (isset($vetor[1]) and $vetor[1] != '') { // LINK
                if (strpos($vetor[1], ".") === false && strpos($vetor[1], "@") !== 0) { // assuming every external link has a dot indicating extension (e.g: .html .txt .zip www.somewhere.com etc.)
                    // Repeated reference to same anchor?
                    while (array_key_exists($vetor[1], $this->internallink)) {
                        $vetor[1] = "#" . $vetor[1];
                    }
                    $this->internallink[$vetor[1]] = $this->AddLink();
                    $vetor[1] = $this->internallink[$vetor[1]];
                }
                $this->HREF = $vetor[1];     // HREF link style set here ******
            }

            // SPECIAL CONTENT - IMAGES & FORM OBJECTS
            // Print-out special content

            if (substr($vetor[0], 0, 3) == "\xbb\xa4\xac") { // identifier has been identified!
                $objattr = $this->_getObjAttr($vetor[0]);

                /* -- TABLES -- */
                if ($objattr['type'] == 'nestedtable') {
                    if ($objattr['nestedcontent']) {
                        $level = $objattr['level'];
                        $table = &$this->table[$level][$objattr['table']];

                        if ($table_draft) {
                            $this->y += $this->table[($level + 1)][$objattr['nestedcontent']]['h']; // nested table height
                            $this->finishFlowingBlock(false, 'nestedtable');
                        } else {
                            $cell = &$table['cells'][$objattr['row']][$objattr['col']];
                            $this->finishFlowingBlock(false, 'nestedtable');
                            $save_dw = $this->divwidth;
                            $save_buffer = $this->cellBorderBuffer;
                            $this->cellBorderBuffer = [];
                            $ncx = $this->x;
                            list($dummyx, $w) = $this->_tableGetWidth($table, $objattr['row'], $objattr['col']);
                            $ntw = $this->table[($level + 1)][$objattr['nestedcontent']]['w']; // nested table width
                            if (!$this->simpleTables) {
                                if ($this->packTableData) {
                                    list($bt, $br, $bb, $bl) = $this->_getBorderWidths($cell['borderbin']);
                                } else {
                                    $br = $cell['border_details']['R']['w'];
                                    $bl = $cell['border_details']['L']['w'];
                                }
                                if ($table['borders_separate']) {
                                    $innerw = $w - $bl - $br - $cell['padding']['L'] - $cell['padding']['R'] - $table['border_spacing_H'];
                                } else {
                                    $innerw = $w - $bl / 2 - $br / 2 - $cell['padding']['L'] - $cell['padding']['R'];
                                }
                            } elseif ($this->simpleTables) {
                                if ($table['borders_separate']) {
                                    $innerw = $w - $table['simple']['border_details']['L']['w'] - $table['simple']['border_details']['R']['w'] - $cell['padding']['L'] - $cell['padding']['R'] - $table['border_spacing_H'];
                                } else {
                                    $innerw = $w - $table['simple']['border_details']['L']['w'] / 2 - $table['simple']['border_details']['R']['w'] / 2 - $cell['padding']['L'] - $cell['padding']['R'];
                                }
                            }
                            if ($cell['a'] == 'C' || $this->table[($level + 1)][$objattr['nestedcontent']]['a'] == 'C') {
                                $ncx += ($innerw - $ntw) / 2;
                            } elseif ($cell['a'] == 'R' || $this->table[($level + 1)][$objattr['nestedcontent']]['a'] == 'R') {
                                $ncx += $innerw - $ntw;
                            }
                            $this->x = $ncx;

                            $this->_tableWrite($this->table[($level + 1)][$objattr['nestedcontent']]);
                            $this->cellBorderBuffer = $save_buffer;
                            $this->x = $bak_x;
                            $this->divwidth = $save_dw;
                        }

                        $this->newFlowingBlock($this->divwidth, $this->divheight, $align, $is_table, $blockstate, false, $blockdir, $table_draft);
                    }
                } else {
                    /* -- END TABLES -- */
                    if ($is_table) { // *TABLES*
                        $maxWidth = $this->divwidth;  // *TABLES*
                    } // *TABLES*
                    else { // *TABLES*
                        $maxWidth = $this->divwidth - ($this->blk[$this->blklvl]['padding_left'] + $this->blk[$this->blklvl]['border_left']['w'] + $this->blk[$this->blklvl]['padding_right'] + $this->blk[$this->blklvl]['border_right']['w']);
                    } // *TABLES*

                    /* -- CSS-IMAGE-FLOAT -- */
                    // If float (already) exists at this level
                    if (isset($this->floatmargins['R']) && $this->y <= $this->floatmargins['R']['y1'] && $this->y >= $this->floatmargins['R']['y0']) {
                        $maxWidth -= $this->floatmargins['R']['w'];
                    }
                    if (isset($this->floatmargins['L']) && $this->y <= $this->floatmargins['L']['y1'] && $this->y >= $this->floatmargins['L']['y0']) {
                        $maxWidth -= $this->floatmargins['L']['w'];
                    }
                    /* -- END CSS-IMAGE-FLOAT -- */

                    list($skipln) = $this->inlineObject($objattr['type'], '', $this->y, $objattr, $this->lMargin, ($this->flowingBlockAttr['contentWidth'] / Mpdf::SCALE), $maxWidth, $this->flowingBlockAttr['height'], false, $is_table);
                    //  1 -> New line needed because of width
                    // -1 -> Will fit width on line but NEW PAGE REQUIRED because of height
                    // -2 -> Will not fit on line therefore needs new line but thus NEW PAGE REQUIRED
                    $iby = $this->y;
                    $oldpage = $this->page;
                    $oldcol = $this->CurrCol;
                    if (($skipln == 1 || $skipln == -2) && !isset($objattr['float'])) {
                        $this->finishFlowingBlock(false, $objattr['type']);
                        $this->newFlowingBlock($this->divwidth, $this->divheight, $align, $is_table, $blockstate, false, $blockdir, $table_draft);
                    }

                    if (!$table_draft) {
                        $thispage = $this->page;
                        if ($this->CurrCol != $oldcol) {
                            $changedcol = true;
                        } else {
                            $changedcol = false;
                        }

                        // the previous lines can already have triggered page break or column change
                        if (!$changedcol && $skipln < 0 && $this->AcceptPageBreak() && $thispage == $oldpage) {
                            $this->AddPage($this->CurOrientation);

                            // Added to correct Images already set on line before page advanced
                            // i.e. if second inline image on line is higher than first and forces new page
                            if (count($this->objectbuffer)) {
                                $yadj = $iby - $this->y;
                                foreach ($this->objectbuffer as $ib => $val) {
                                    if ($this->objectbuffer[$ib]['OUTER-Y']) {
                                        $this->objectbuffer[$ib]['OUTER-Y'] -= $yadj;
                                    }
                                    if ($this->objectbuffer[$ib]['BORDER-Y']) {
                                        $this->objectbuffer[$ib]['BORDER-Y'] -= $yadj;
                                    }
                                    if ($this->objectbuffer[$ib]['INNER-Y']) {
                                        $this->objectbuffer[$ib]['INNER-Y'] -= $yadj;
                                    }
                                }
                            }
                        }

                        // Added to correct for OddEven Margins
                        if ($this->page != $oldpage) {
                            if (($this->page - $oldpage) % 2 == 1) {
                                $bak_x += $this->MarginCorrection;
                            }
                            $oldpage = $this->page;
                            $y = $this->tMargin - $paint_ht_corr;
                            $this->oldy = $this->tMargin - $paint_ht_corr;
                            $old_height = 0;
                        }
                        $this->x = $bak_x;
                        /* -- COLUMNS -- */
                        // COLS
                        // OR COLUMN CHANGE
                        if ($this->CurrCol != $oldcolumn) {
                            if ($this->directionality == 'rtl') { // *OTL*
                                $bak_x -= ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap); // *OTL*
                            } // *OTL*
                            else { // *OTL*
                                $bak_x += ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap);
                            } // *OTL*
                            $this->x = $bak_x;
                            $oldcolumn = $this->CurrCol;
                            $y = $this->y0 - $paint_ht_corr;
                            $this->oldy = $this->y0 - $paint_ht_corr;
                            $old_height = 0;
                        }
                        /* -- END COLUMNS -- */
                    }

                    /* -- CSS-IMAGE-FLOAT -- */
                    if ($objattr['type'] == 'image' && isset($objattr['float'])) {
                        $fy = $this->y;

                        // DIV TOP MARGIN/BORDER/PADDING
                        if ($this->flowingBlockAttr['newblock'] && ($this->flowingBlockAttr['blockstate'] == 1 || $this->flowingBlockAttr['blockstate'] == 3) && $this->flowingBlockAttr['lineCount'] == 0) {
                            $fy += $this->blk[$this->blklvl]['margin_top'] + $this->blk[$this->blklvl]['padding_top'] + $this->blk[$this->blklvl]['border_top']['w'];
                        }

                        if ($objattr['float'] == 'R') {
                            $fx = $this->w - $this->rMargin - $objattr['width'] - ($this->blk[$this->blklvl]['outer_right_margin'] + $this->blk[$this->blklvl]['border_right']['w'] + $this->blk[$this->blklvl]['padding_right']);
                        } elseif ($objattr['float'] == 'L') {
                            $fx = $this->lMargin + ($this->blk[$this->blklvl]['outer_left_margin'] + $this->blk[$this->blklvl]['border_left']['w'] + $this->blk[$this->blklvl]['padding_left']);
                        }
                        $w = $objattr['width'];
                        $h = abs($objattr['height']);

                        $widthLeft = $maxWidth - ($this->flowingBlockAttr['contentWidth'] / Mpdf::SCALE);
                        $maxHeight = $this->h - ($this->tMargin + $this->margin_header + $this->bMargin + 10);
                        // For Images
                        $extraWidth = ($objattr['border_left']['w'] + $objattr['border_right']['w'] + $objattr['margin_left'] + $objattr['margin_right']);
                        $extraHeight = ($objattr['border_top']['w'] + $objattr['border_bottom']['w'] + $objattr['margin_top'] + $objattr['margin_bottom']);

                        if ($objattr['itype'] == 'wmf' || $objattr['itype'] == 'svg') {
                            $file = $objattr['file'];
                            $info = $this->formobjects[$file];
                        } else {
                            $file = $objattr['file'];
                            $info = $this->images[$file];
                        }
                        $img_w = $w - $extraWidth;
                        $img_h = $h - $extraHeight;
                        if ($objattr['border_left']['w']) {
                            $objattr['BORDER-WIDTH'] = $img_w + (($objattr['border_left']['w'] + $objattr['border_right']['w']) / 2);
                            $objattr['BORDER-HEIGHT'] = $img_h + (($objattr['border_top']['w'] + $objattr['border_bottom']['w']) / 2);
                            $objattr['BORDER-X'] = $fx + $objattr['margin_left'] + (($objattr['border_left']['w']) / 2);
                            $objattr['BORDER-Y'] = $fy + $objattr['margin_top'] + (($objattr['border_top']['w']) / 2);
                        }
                        $objattr['INNER-WIDTH'] = $img_w;
                        $objattr['INNER-HEIGHT'] = $img_h;
                        $objattr['INNER-X'] = $fx + $objattr['margin_left'] + ($objattr['border_left']['w']);
                        $objattr['INNER-Y'] = $fy + $objattr['margin_top'] + ($objattr['border_top']['w']);
                        $objattr['ID'] = $info['i'];
                        $objattr['OUTER-WIDTH'] = $w;
                        $objattr['OUTER-HEIGHT'] = $h;
                        $objattr['OUTER-X'] = $fx;
                        $objattr['OUTER-Y'] = $fy;
                        if ($objattr['float'] == 'R') {
                            // If R float already exists at this level
                            $this->floatmargins['R']['skipline'] = false;
                            if (isset($this->floatmargins['R']['y1']) && $this->floatmargins['R']['y1'] > 0 && $fy < $this->floatmargins['R']['y1']) {
                                $this->WriteFlowingBlock($vetor[0], $vetor[18]);  // mPDF 5.7.1
                            } // If L float already exists at this level
                            elseif (isset($this->floatmargins['L']['y1']) && $this->floatmargins['L']['y1'] > 0 && $fy < $this->floatmargins['L']['y1']) {
                                // Final check distance between floats is not now too narrow to fit text
                                $mw = 2 * $this->GetCharWidth('W', false);
                                if (($this->blk[$this->blklvl]['inner_width'] - $w - $this->floatmargins['L']['w']) < $mw) {
                                    $this->WriteFlowingBlock($vetor[0], $vetor[18]);  // mPDF 5.7.1
                                } else {
                                    $this->floatmargins['R']['x'] = $fx;
                                    $this->floatmargins['R']['w'] = $w;
                                    $this->floatmargins['R']['y0'] = $fy;
                                    $this->floatmargins['R']['y1'] = $fy + $h;
                                    if ($skipln == 1) {
                                        $this->floatmargins['R']['skipline'] = true;
                                        $this->floatmargins['R']['id'] = count($this->floatbuffer) + 0;
                                        $objattr['skipline'] = true;
                                    }
                                    $this->floatbuffer[] = $objattr;
                                }
                            } else {
                                $this->floatmargins['R']['x'] = $fx;
                                $this->floatmargins['R']['w'] = $w;
                                $this->floatmargins['R']['y0'] = $fy;
                                $this->floatmargins['R']['y1'] = $fy + $h;
                                if ($skipln == 1) {
                                    $this->floatmargins['R']['skipline'] = true;
                                    $this->floatmargins['R']['id'] = count($this->floatbuffer) + 0;
                                    $objattr['skipline'] = true;
                                }
                                $this->floatbuffer[] = $objattr;
                            }
                        } elseif ($objattr['float'] == 'L') {
                            // If L float already exists at this level
                            $this->floatmargins['L']['skipline'] = false;
                            if (isset($this->floatmargins['L']['y1']) && $this->floatmargins['L']['y1'] > 0 && $fy < $this->floatmargins['L']['y1']) {
                                $this->floatmargins['L']['skipline'] = false;
                                $this->WriteFlowingBlock($vetor[0], $vetor[18]);  // mPDF 5.7.1
                            } // If R float already exists at this level
                            elseif (isset($this->floatmargins['R']['y1']) && $this->floatmargins['R']['y1'] > 0 && $fy < $this->floatmargins['R']['y1']) {
                                // Final check distance between floats is not now too narrow to fit text
                                $mw = 2 * $this->GetCharWidth('W', false);
                                if (($this->blk[$this->blklvl]['inner_width'] - $w - $this->floatmargins['R']['w']) < $mw) {
                                    $this->WriteFlowingBlock($vetor[0], $vetor[18]);  // mPDF 5.7.1
                                } else {
                                    $this->floatmargins['L']['x'] = $fx + $w;
                                    $this->floatmargins['L']['w'] = $w;
                                    $this->floatmargins['L']['y0'] = $fy;
                                    $this->floatmargins['L']['y1'] = $fy + $h;
                                    if ($skipln == 1) {
                                        $this->floatmargins['L']['skipline'] = true;
                                        $this->floatmargins['L']['id'] = count($this->floatbuffer) + 0;
                                        $objattr['skipline'] = true;
                                    }
                                    $this->floatbuffer[] = $objattr;
                                }
                            } else {
                                $this->floatmargins['L']['x'] = $fx + $w;
                                $this->floatmargins['L']['w'] = $w;
                                $this->floatmargins['L']['y0'] = $fy;
                                $this->floatmargins['L']['y1'] = $fy + $h;
                                if ($skipln == 1) {
                                    $this->floatmargins['L']['skipline'] = true;
                                    $this->floatmargins['L']['id'] = count($this->floatbuffer) + 0;
                                    $objattr['skipline'] = true;
                                }
                                $this->floatbuffer[] = $objattr;
                            }
                        }
                    } else {
                        /* -- END CSS-IMAGE-FLOAT -- */
                        $this->WriteFlowingBlock($vetor[0], (isset($vetor[18]) ? $vetor[18] : null));  // mPDF 5.7.1
                        /* -- CSS-IMAGE-FLOAT -- */
                    }
                    /* -- END CSS-IMAGE-FLOAT -- */
                } // *TABLES*
            } // END If special content
            else { // THE text
                if ($this->tableLevel) {
                    $paint_ht_corr = 0;
                } // To move the y up when new column/page started if div border needed
                else {
                    $paint_ht_corr = $this->blk[$this->blklvl]['border_top']['w'];
                }

                if ($vetor[0] == "\n") { // We are reading a <BR> now turned into newline ("\n")
                    if ($this->flowingBlockAttr['content']) {
                        $this->finishFlowingBlock(false, 'br');
                    } elseif ($is_table) {
                        $this->y+= $this->_computeLineheight($this->cellLineHeight);
                    } elseif (!$is_table) {
                        $this->DivLn($this->lineheight);
                        if ($this->ColActive) {
                            $this->breakpoints[$this->CurrCol][] = $this->y;
                        } // *COLUMNS*
                    }
                    // Added to correct for OddEven Margins
                    if ($this->page != $oldpage) {
                        if (($this->page - $oldpage) % 2 == 1) {
                            $bak_x += $this->MarginCorrection;
                        }
                        $oldpage = $this->page;
                        $y = $this->tMargin - $paint_ht_corr;
                        $this->oldy = $this->tMargin - $paint_ht_corr;
                        $old_height = 0;
                    }
                    $this->x = $bak_x;
                    /* -- COLUMNS -- */
                    // COLS
                    // OR COLUMN CHANGE
                    if ($this->CurrCol != $oldcolumn) {
                        if ($this->directionality == 'rtl') { // *OTL*
                            $bak_x -= ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap); // *OTL*
                        } // *OTL*
                        else { // *OTL*
                            $bak_x += ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap);
                        } // *OTL*
                        $this->x = $bak_x;
                        $oldcolumn = $this->CurrCol;
                        $y = $this->y0 - $paint_ht_corr;
                        $this->oldy = $this->y0 - $paint_ht_corr;
                        $old_height = 0;
                    }
                    /* -- END COLUMNS -- */
                    $this->newFlowingBlock($this->divwidth, $this->divheight, $align, $is_table, $blockstate, false, $blockdir, $table_draft);
                } else {
                    $this->WriteFlowingBlock($vetor[0], $vetor[18]);  // mPDF 5.7.1
                    // Added to correct for OddEven Margins
                    if ($this->page != $oldpage) {
                        if (($this->page - $oldpage) % 2 == 1) {
                            $bak_x += $this->MarginCorrection;
                            $this->x = $bak_x;
                        }
                        $oldpage = $this->page;
                        $y = $this->tMargin - $paint_ht_corr;
                        $this->oldy = $this->tMargin - $paint_ht_corr;
                        $old_height = 0;
                    }
                    /* -- COLUMNS -- */
                    // COLS
                    // OR COLUMN CHANGE
                    if ($this->CurrCol != $oldcolumn) {
                        if ($this->directionality == 'rtl') { // *OTL*
                            $bak_x -= ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap); // *OTL*
                        } // *OTL*
                        else { // *OTL*
                            $bak_x += ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap);
                        } // *OTL*
                        $this->x = $bak_x;
                        $oldcolumn = $this->CurrCol;
                        $y = $this->y0 - $paint_ht_corr;
                        $this->oldy = $this->y0 - $paint_ht_corr;
                        $old_height = 0;
                    }
                    /* -- END COLUMNS -- */
                }
            }

            // Check if it is the last element. If so then finish printing the block
            if ($i == ($array_size - 1)) {
                $this->finishFlowingBlock(true); // true = END of flowing block
                // Added to correct for OddEven Margins
                if ($this->page != $oldpage) {
                    if (($this->page - $oldpage) % 2 == 1) {
                        $bak_x += $this->MarginCorrection;
                        $this->x = $bak_x;
                    }
                    $oldpage = $this->page;
                    $y = $this->tMargin - $paint_ht_corr;
                    $this->oldy = $this->tMargin - $paint_ht_corr;
                    $old_height = 0;
                }

                /* -- COLUMNS -- */
                // COLS
                // OR COLUMN CHANGE
                if ($this->CurrCol != $oldcolumn) {
                    if ($this->directionality == 'rtl') { // *OTL*
                        $bak_x -= ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap); // *OTL*
                    } // *OTL*
                    else { // *OTL*
                        $bak_x += ($this->CurrCol - $oldcolumn) * ($this->ColWidth + $this->ColGap);
                    } // *OTL*
                    $this->x = $bak_x;
                    $oldcolumn = $this->CurrCol;
                    $y = $this->y0 - $paint_ht_corr;
                    $this->oldy = $this->y0 - $paint_ht_corr;
                    $old_height = 0;
                }
                /* -- END COLUMNS -- */
            }

            // RESETTING VALUES
            $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
            $this->colorarray = '';
            $this->spanbgcolorarray = '';
            $this->spanbgcolor = false;
            $this->spanborder = false;
            $this->spanborddet = [];
            $this->HREF = '';
            $this->textparam = [];
            $this->SetTextOutline();

            $this->textvar = 0x00; // mPDF 5.7.1
            $this->OTLtags = [];
            $this->textshadow = '';

            $this->currentfontfamily = '';
            $this->currentfontsize = '';
            $this->currentfontstyle = '';
            $this->currentLang = $this->default_lang;  // mPDF 6
            $this->RestrictUnicodeFonts($this->default_available_fonts); // mPDF 6
            /* -- TABLES -- */
            if ($this->tableLevel) {
                $this->SetLineHeight('', $this->table[1][1]['cellLineHeight']); // *TABLES*
            } else {            /* -- END TABLES -- */
                if (isset($this->blk[$this->blklvl]['line_height']) && $this->blk[$this->blklvl]['line_height']) {
                    $this->SetLineHeight('', $this->blk[$this->blklvl]['line_height']); // sets default line height
                }
            }
            $this->ResetStyles();
            $this->lSpacingCSS = '';
            $this->wSpacingCSS = '';
            $this->fixedlSpacing = false;
            $this->minwSpacing = 0;
            $this->SetDash();
            $this->dash_on = false;
            $this->dotted_on = false;
        }//end of for(i=0;i<arraysize;i++)

        $this->Reset(); // mPDF 6
        // PAINT DIV BORDER // DISABLED IN COLUMNS AS DOESN'T WORK WHEN BROKEN ACROSS COLS??
        if ((isset($this->blk[$this->blklvl]['border']) || isset($this->blk[$this->blklvl]['bgcolor']) || isset($this->blk[$this->blklvl]['box_shadow'])) && $blockstate && ($this->y != $this->oldy)) {
            $bottom_y = $this->y; // Does not include Bottom Margin
            if (isset($this->blk[$this->blklvl]['startpage']) && $this->blk[$this->blklvl]['startpage'] != $this->page && $blockstate != 1) {
                $this->PaintDivBB('pagetop', $blockstate);
            } elseif ($blockstate != 1) {
                $this->PaintDivBB('', $blockstate);
            }
            $this->y = $bottom_y;
            $this->x = $bak_x;
        }

        // Reset Font
        $this->SetFontSize($this->default_font_size, false);
        if ($table_draft) {
            $ch = $this->y - $bak_y;
            $this->y = $bak_y;
            $this->x = $bak_x;
            return $ch;
        }
    }

    public function PaintDivBB($divider = '', $blockstate = 0, $blvl = 0)
    {
        // Borders & backgrounds are done elsewhere for columns - messes up the repositioning in printcolumnbuffer
        if ($this->ColActive) {
            return;
        } // *COLUMNS*
        if ($this->keep_block_together) {
            return;
        } // mPDF 6
        $save_y = $this->y;
        if (!$blvl) {
            $blvl = $this->blklvl;
        }
        $x0 = $x1 = $y0 = $y1 = 0;

        // Added mPDF 3.0 Float DIV
        if (isset($this->blk[$blvl]['bb_painted'][$this->page]) && $this->blk[$blvl]['bb_painted'][$this->page]) {
            return;
        } // *CSS-FLOAT*

        if (isset($this->blk[$blvl]['x0'])) {
            $x0 = $this->blk[$blvl]['x0'];
        } // left
        if (isset($this->blk[$blvl]['y1'])) {
            $y1 = $this->blk[$blvl]['y1'];
        } // bottom
        // Added mPDF 3.0 Float DIV - ensures backgrounds/borders are drawn to bottom of page
        if ($y1 == 0) {
            if ($divider == 'pagebottom') {
                $y1 = $this->h - $this->bMargin;
            } else {
                $y1 = $this->y;
            }
        }

        if (isset($this->blk[$blvl]['startpage']) && $this->blk[$blvl]['startpage'] != $this->page) {
            $continuingpage = true;
        } else {
            $continuingpage = false;
        }

        if (isset($this->blk[$blvl]['y0'])) {
            $y0 = $this->blk[$blvl]['y0'];
        }
        $h = $y1 - $y0;
        $w = $this->blk[$blvl]['width'];
        $x1 = $x0 + $w;

        // Set border-widths as used here
        $border_top = $this->blk[$blvl]['border_top']['w'];
        $border_bottom = $this->blk[$blvl]['border_bottom']['w'];
        $border_left = $this->blk[$blvl]['border_left']['w'];
        $border_right = $this->blk[$blvl]['border_right']['w'];
        if (!$this->blk[$blvl]['border_top'] || $divider == 'pagetop' || $continuingpage) {
            $border_top = 0;
        }
        if (!$this->blk[$blvl]['border_bottom'] || $blockstate == 1 || $divider == 'pagebottom') {
            $border_bottom = 0;
        }

        $brTL_H = 0;
        $brTL_V = 0;
        $brTR_H = 0;
        $brTR_V = 0;
        $brBL_H = 0;
        $brBL_V = 0;
        $brBR_H = 0;
        $brBR_V = 0;

        $brset = false;
        /* -- BORDER-RADIUS -- */
        if (isset($this->blk[$blvl]['border_radius_TL_H'])) {
            $brTL_H = $this->blk[$blvl]['border_radius_TL_H'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_TL_V'])) {
            $brTL_V = $this->blk[$blvl]['border_radius_TL_V'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_TR_H'])) {
            $brTR_H = $this->blk[$blvl]['border_radius_TR_H'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_TR_V'])) {
            $brTR_V = $this->blk[$blvl]['border_radius_TR_V'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_BR_H'])) {
            $brBR_H = $this->blk[$blvl]['border_radius_BR_H'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_BR_V'])) {
            $brBR_V = $this->blk[$blvl]['border_radius_BR_V'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_BL_H'])) {
            $brBL_H = $this->blk[$blvl]['border_radius_BL_H'];
            $brset = true;
        }
        if (isset($this->blk[$blvl]['border_radius_BL_V'])) {
            $brBL_V = $this->blk[$blvl]['border_radius_BL_V'];
            $brset = true;
        }

        if (!$this->blk[$blvl]['border_top'] || $divider == 'pagetop' || $continuingpage) {
            $brTL_H = 0;
            $brTL_V = 0;
            $brTR_H = 0;
            $brTR_V = 0;
        }
        if (!$this->blk[$blvl]['border_bottom'] || $blockstate == 1 || $divider == 'pagebottom') {
            $brBL_H = 0;
            $brBL_V = 0;
            $brBR_H = 0;
            $brBR_V = 0;
        }

        // Disallow border-radius if it is smaller than the border width.
        if ($brTL_H < min($border_left, $border_top)) {
            $brTL_H = $brTL_V = 0;
        }
        if ($brTL_V < min($border_left, $border_top)) {
            $brTL_V = $brTL_H = 0;
        }
        if ($brTR_H < min($border_right, $border_top)) {
            $brTR_H = $brTR_V = 0;
        }
        if ($brTR_V < min($border_right, $border_top)) {
            $brTR_V = $brTR_H = 0;
        }
        if ($brBL_H < min($border_left, $border_bottom)) {
            $brBL_H = $brBL_V = 0;
        }
        if ($brBL_V < min($border_left, $border_bottom)) {
            $brBL_V = $brBL_H = 0;
        }
        if ($brBR_H < min($border_right, $border_bottom)) {
            $brBR_H = $brBR_V = 0;
        }
        if ($brBR_V < min($border_right, $border_bottom)) {
            $brBR_V = $brBR_H = 0;
        }

        // CHECK FOR radii that sum to > width or height of div ********
        $f = min($h / ($brTL_V + $brBL_V + 0.001), $h / ($brTR_V + $brBR_V + 0.001), $w / ($brTL_H + $brTR_H + 0.001), $w / ($brBL_H + $brBR_H + 0.001));
        if ($f < 1) {
            $brTL_H *= $f;
            $brTL_V *= $f;
            $brTR_H *= $f;
            $brTR_V *= $f;
            $brBL_H *= $f;
            $brBL_V *= $f;
            $brBR_H *= $f;
            $brBR_V *= $f;
        }
        /* -- END BORDER-RADIUS -- */

        $tbcol = $this->colorConverter->convert(255, $this->PDFAXwarnings);
        for ($l = 0; $l <= $blvl; $l++) {
            if ($this->blk[$l]['bgcolor']) {
                $tbcol = $this->blk[$l]['bgcolorarray'];
            }
        }

        // BORDERS
        if (isset($this->blk[$blvl]['y0']) && $this->blk[$blvl]['y0']) {
            $y0 = $this->blk[$blvl]['y0'];
        }
        $h = $y1 - $y0;
        $w = $this->blk[$blvl]['width'];

        if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
            $tbd = $this->blk[$blvl]['border_top'];

            $legend = '';
            $legbreakL = 0;
            $legbreakR = 0;
            // BORDER LEGEND
            if (isset($this->blk[$blvl]['border_legend']) && $this->blk[$blvl]['border_legend']) {
                $legend = $this->blk[$blvl]['border_legend']; // Same structure array as textbuffer
                $txt = $legend[0] = ltrim($legend[0]);
                if (!empty($legend[18])) {
                    $this->otl->trimOTLdata($legend[18], true, false);
                } // *OTL*
                // Set font, size, style, color
                $this->SetFont($legend[4], $legend[2], $legend[11]);
                if (isset($legend[3]) && $legend[3]) {
                    $cor = $legend[3];
                    $this->SetTColor($cor);
                }
                $stringWidth = $this->GetStringWidth($txt, true, $legend[18], $legend[8]);
                $save_x = $this->x;
                $save_y = $this->y;
                $save_currentfontfamily = $this->FontFamily;
                $save_currentfontsize = $this->FontSizePt;
                $save_currentfontstyle = $this->FontStyle;
                $this->y = $y0 - $this->FontSize / 2 + $this->blk[$blvl]['border_top']['w'] / 2;
                $this->x = $x0 + $this->blk[$blvl]['padding_left'] + $this->blk[$blvl]['border_left']['w'];

                // Set the distance from the border line to the text ? make configurable variable
                $gap = 0.2 * $this->FontSize;
                $legbreakL = $this->x - $gap;
                $legbreakR = $this->x + $stringWidth + $gap;
                $this->magic_reverse_dir($txt, $this->blk[$blvl]['direction'], $legend[18]);
                $fill = '';
                $this->Cell($stringWidth, $this->FontSize, $txt, '', 0, 'C', $fill, '', 0, 0, 0, 'M', $fill, false, $legend[18], $legend[8]);
                // Reset
                $this->x = $save_x;
                $this->y = $save_y;
                $this->SetFont($save_currentfontfamily, $save_currentfontstyle, $save_currentfontsize);
                $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            }

            if (isset($tbd['s']) && $tbd['s']) {
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('q');
                    $this->SetLineWidth(0);
                    $this->_out(sprintf('%.3F %.3F m ', ($x0) * Mpdf::SCALE, ($this->h - ($y0)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $border_left) * Mpdf::SCALE, ($this->h - ($y0 + $border_top)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w - $border_right) * Mpdf::SCALE, ($this->h - ($y0 + $border_top)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0)) * Mpdf::SCALE));
                    $this->_out(' h W n '); // Ends path no-op & Sets the clipping path
                }

                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $legbreakL -= $border_top / 2; // because line cap different
                    $legbreakR += $border_top / 2;
                    $this->_setDashBorder($tbd['style'], $divider, $continuingpage, 'T');
                } /* -- BORDER-RADIUS -- */ elseif (($brTL_V && $brTL_H) || ($brTR_V && $brTR_H) || $tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                }
                $s = '';
                if ($brTR_H && $brTR_V) {
                    $s .= ($this->_EllipseArc($x0 + $w - $brTR_H, $y0 + $brTR_V, $brTR_H - $border_top / 2, $brTR_V - $border_top / 2, 1, 2, true)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + $w - ($border_top / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                    }
                }
                /* -- BORDER-RADIUS -- */
                if ($brTL_H && $brTL_V) {
                    if ($legend) {
                        if ($legbreakR < ($x0 + $w - $brTR_H)) {
                            $s .= (sprintf('%.3F %.3F l ', $legbreakR * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                        }
                        if ($legbreakL > ($x0 + $brTL_H )) {
                            $s .= (sprintf('%.3F %.3F m ', $legbreakL * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                            $s .= (sprintf('%.3F %.3F l ', ($x0 + $brTL_H ) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE) . "\n");
                        } else {
                            $s .= (sprintf('%.3F %.3F m ', ($x0 + $brTL_H ) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                        }
                    } else {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + $brTL_H ) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                    }
                    $s .= ($this->_EllipseArc($x0 + $brTL_H, $y0 + $brTL_V, $brTL_H - $border_top / 2, $brTL_V - $border_top / 2, 2, 1)) . "\n";
                } else {
                    /* -- END BORDER-RADIUS -- */
                    if ($legend) {
                        if ($legbreakR < ($x0 + $w)) {
                            $s .= (sprintf('%.3F %.3F l ', $legbreakR * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                        }
                        if ($legbreakL > ($x0)) {
                            $s .= (sprintf('%.3F %.3F m ', $legbreakL * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                            if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                                $s .= (sprintf('%.3F %.3F l ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                            } else {
                                $s .= (sprintf('%.3F %.3F l ', ($x0 + ($border_top / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                            }
                        } elseif ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                            $s .= (sprintf('%.3F %.3F m ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                        } else {
                            $s .= (sprintf('%.3F %.3F m ', ($x0 + $border_top / 2) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                        }
                    } elseif ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F l ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + ($border_top / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_top / 2))) * Mpdf::SCALE)) . "\n";
                    }
                    /* -- BORDER-RADIUS -- */
                }
                /* -- END BORDER-RADIUS -- */
                $s .= 'S' . "\n";
                $this->_out($s);

                if ($tbd['style'] == 'double') {
                    $this->SetLineWidth($tbd['w'] / 3);
                    $this->SetDColor($tbcol);
                    $this->_out($s);
                }
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('Q');
                }

                // Reset Corners and Dash off
                $this->SetLineWidth(0.1);
                $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        // Reinstate line above for dotted line divider when block border crosses a page
        // elseif ($divider == 'pagetop' || $continuingpage) {

        if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
            $tbd = $this->blk[$blvl]['border_bottom'];
            if (isset($tbd['s']) && $tbd['s']) {
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('q');
                    $this->SetLineWidth(0);
                    $this->_out(sprintf('%.3F %.3F m ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + $h)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $border_left) * Mpdf::SCALE, ($this->h - ($y0 + $h - $border_bottom)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w - $border_right) * Mpdf::SCALE, ($this->h - ($y0 + $h - $border_bottom)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0 + $h)) * Mpdf::SCALE));
                    $this->_out(' h W n '); // Ends path no-op & Sets the clipping path
                }

                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->_setDashBorder($tbd['style'], $divider, $continuingpage, 'B');
                } /* -- BORDER-RADIUS -- */ elseif (($brBL_V && $brBL_H) || ($brBR_V && $brBR_H) || $tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                }
                $s = '';
                if ($brBL_H && $brBL_V) {
                    $s .= ($this->_EllipseArc($x0 + $brBL_H, $y0 + $h - $brBL_V, $brBL_H - $border_bottom / 2, $brBL_V - $border_bottom / 2, 3, 2, true)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F m ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_bottom / 2))) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + ($border_bottom / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_bottom / 2))) * Mpdf::SCALE)) . "\n";
                    }
                }
                /* -- BORDER-RADIUS -- */
                if ($brBR_H && $brBR_V) {
                    $s .= (sprintf('%.3F %.3F l ', ($x0 + $w - ($border_bottom / 2) - $brBR_H ) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_bottom / 2))) * Mpdf::SCALE)) . "\n";
                    $s .= ($this->_EllipseArc($x0 + $w - $brBR_H, $y0 + $h - $brBR_V, $brBR_H - $border_bottom / 2, $brBR_V - $border_bottom / 2, 4, 1)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_bottom / 2))) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + $w - ($border_bottom / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_bottom / 2))) * Mpdf::SCALE)) . "\n";
                    }
                }
                $s .= 'S' . "\n";
                $this->_out($s);

                if ($tbd['style'] == 'double') {
                    $this->SetLineWidth($tbd['w'] / 3);
                    $this->SetDColor($tbcol);
                    $this->_out($s);
                }
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('Q');
                }

                // Reset Corners and Dash off
                $this->SetLineWidth(0.1);
                $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        // Reinstate line below for dotted line divider when block border crosses a page
        // elseif ($blockstate == 1 || $divider == 'pagebottom') {

        if ($this->blk[$blvl]['border_left']) {
            $tbd = $this->blk[$blvl]['border_left'];
            if (isset($tbd['s']) && $tbd['s']) {
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('q');
                    $this->SetLineWidth(0);
                    $this->_out(sprintf('%.3F %.3F m ', ($x0) * Mpdf::SCALE, ($this->h - ($y0)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $border_left) * Mpdf::SCALE, ($this->h - ($y0 + $border_top)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $border_left) * Mpdf::SCALE, ($this->h - ($y0 + $h - $border_bottom)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0) * Mpdf::SCALE, ($this->h - ($y0 + $h)) * Mpdf::SCALE));
                    $this->_out(' h W n '); // Ends path no-op & Sets the clipping path
                }

                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->_setDashBorder($tbd['style'], $divider, $continuingpage, 'L');
                } /* -- BORDER-RADIUS -- */ elseif (($brTL_V && $brTL_H) || ($brBL_V && $brBL_H) || $tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                }
                $s = '';
                if ($brTL_V && $brTL_H) {
                    $s .= ($this->_EllipseArc($x0 + $brTL_H, $y0 + $brTL_V, $brTL_H - $border_left / 2, $brTL_V - $border_left / 2, 2, 2, true)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + ($border_left / 2)) * Mpdf::SCALE, ($this->h - ($y0)) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + ($border_left / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_left / 2))) * Mpdf::SCALE)) . "\n";
                    }
                }
                /* -- BORDER-RADIUS -- */
                if ($brBL_V && $brBL_H) {
                    $s .= (sprintf('%.3F %.3F l ', ($x0 + ($border_left / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_left / 2) - $brBL_V) ) * Mpdf::SCALE)) . "\n";
                    $s .= ($this->_EllipseArc($x0 + $brBL_H, $y0 + $h - $brBL_V, $brBL_H - $border_left / 2, $brBL_V - $border_left / 2, 3, 1)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + ($border_left / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h) ) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + ($border_left / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_left / 2)) ) * Mpdf::SCALE)) . "\n";
                    }
                }
                $s .= 'S' . "\n";
                $this->_out($s);

                if ($tbd['style'] == 'double') {
                    $this->SetLineWidth($tbd['w'] / 3);
                    $this->SetDColor($tbcol);
                    $this->_out($s);
                }
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('Q');
                }

                // Reset Corners and Dash off
                $this->SetLineWidth(0.1);
                $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        if ($this->blk[$blvl]['border_right']) {
            $tbd = $this->blk[$blvl]['border_right'];
            if (isset($tbd['s']) && $tbd['s']) {
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('q');
                    $this->SetLineWidth(0);
                    $this->_out(sprintf('%.3F %.3F m ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w - $border_right) * Mpdf::SCALE, ($this->h - ($y0 + $border_top)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w - $border_right) * Mpdf::SCALE, ($this->h - ($y0 + $h - $border_bottom)) * Mpdf::SCALE));
                    $this->_out(sprintf('%.3F %.3F l ', ($x0 + $w) * Mpdf::SCALE, ($this->h - ($y0 + $h)) * Mpdf::SCALE));
                    $this->_out(' h W n '); // Ends path no-op & Sets the clipping path
                }

                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->_setDashBorder($tbd['style'], $divider, $continuingpage, 'R');
                } /* -- BORDER-RADIUS -- */ elseif (($brTR_V && $brTR_H) || ($brBR_V && $brBR_H) || $tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                }
                $s = '';
                if ($brBR_V && $brBR_H) {
                    $s .= ($this->_EllipseArc($x0 + $w - $brBR_H, $y0 + $h - $brBR_V, $brBR_H - $border_right / 2, $brBR_V - $border_right / 2, 4, 2, true)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + $w - ($border_right / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h)) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F m ', ($x0 + $w - ($border_right / 2)) * Mpdf::SCALE, ($this->h - ($y0 + $h - ($border_right / 2))) * Mpdf::SCALE)) . "\n";
                    }
                }
                /* -- BORDER-RADIUS -- */
                if ($brTR_V && $brTR_H) {
                    $s .= (sprintf('%.3F %.3F l ', ($x0 + $w - ($border_right / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_right / 2) + $brTR_V) ) * Mpdf::SCALE)) . "\n";
                    $s .= ($this->_EllipseArc($x0 + $w - $brTR_H, $y0 + $brTR_V, $brTR_H - $border_right / 2, $brTR_V - $border_right / 2, 1, 1)) . "\n";
                } else {                /* -- END BORDER-RADIUS -- */
                    if ($tbd['style'] == 'solid' || $tbd['style'] == 'double') {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + $w - ($border_right / 2)) * Mpdf::SCALE, ($this->h - ($y0) ) * Mpdf::SCALE)) . "\n";
                    } else {
                        $s .= (sprintf('%.3F %.3F l ', ($x0 + $w - ($border_right / 2)) * Mpdf::SCALE, ($this->h - ($y0 + ($border_right / 2)) ) * Mpdf::SCALE)) . "\n";
                    }
                }
                $s .= 'S' . "\n";
                $this->_out($s);

                if ($tbd['style'] == 'double') {
                    $this->SetLineWidth($tbd['w'] / 3);
                    $this->SetDColor($tbcol);
                    $this->_out($s);
                }
                if (!$brset && $tbd['style'] != 'dotted' && $tbd['style'] != 'dashed') {
                    $this->_out('Q');
                }

                // Reset Corners and Dash off
                $this->SetLineWidth(0.1);
                $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }


        $this->SetDash();
        $this->y = $save_y;


        // BACKGROUNDS are disabled in columns/kbt/headers - messes up the repositioning in printcolumnbuffer
        if ($this->ColActive || $this->kwt || $this->keep_block_together) {
            return;
        }


        $bgx0 = $x0;
        $bgx1 = $x1;
        $bgy0 = $y0;
        $bgy1 = $y1;

        // Defined br values represent the radius of the outer curve - need to take border-width/2 from each radius for drawing the borders
        if (isset($this->blk[$blvl]['background_clip']) && $this->blk[$blvl]['background_clip'] == 'padding-box') {
            $brbgTL_H = max(0, $brTL_H - $this->blk[$blvl]['border_left']['w']);
            $brbgTL_V = max(0, $brTL_V - $this->blk[$blvl]['border_top']['w']);
            $brbgTR_H = max(0, $brTR_H - $this->blk[$blvl]['border_right']['w']);
            $brbgTR_V = max(0, $brTR_V - $this->blk[$blvl]['border_top']['w']);
            $brbgBL_H = max(0, $brBL_H - $this->blk[$blvl]['border_left']['w']);
            $brbgBL_V = max(0, $brBL_V - $this->blk[$blvl]['border_bottom']['w']);
            $brbgBR_H = max(0, $brBR_H - $this->blk[$blvl]['border_right']['w']);
            $brbgBR_V = max(0, $brBR_V - $this->blk[$blvl]['border_bottom']['w']);
            $bgx0 += $this->blk[$blvl]['border_left']['w'];
            $bgx1 -= $this->blk[$blvl]['border_right']['w'];
            if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
                $bgy0 += $this->blk[$blvl]['border_top']['w'];
            }
            if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
                $bgy1 -= $this->blk[$blvl]['border_bottom']['w'];
            }
        } elseif (isset($this->blk[$blvl]['background_clip']) && $this->blk[$blvl]['background_clip'] == 'content-box') {
            $brbgTL_H = max(0, $brTL_H - $this->blk[$blvl]['border_left']['w'] - $this->blk[$blvl]['padding_left']);
            $brbgTL_V = max(0, $brTL_V - $this->blk[$blvl]['border_top']['w'] - $this->blk[$blvl]['padding_top']);
            $brbgTR_H = max(0, $brTR_H - $this->blk[$blvl]['border_right']['w'] - $this->blk[$blvl]['padding_right']);
            $brbgTR_V = max(0, $brTR_V - $this->blk[$blvl]['border_top']['w'] - $this->blk[$blvl]['padding_top']);
            $brbgBL_H = max(0, $brBL_H - $this->blk[$blvl]['border_left']['w'] - $this->blk[$blvl]['padding_left']);
            $brbgBL_V = max(0, $brBL_V - $this->blk[$blvl]['border_bottom']['w'] - $this->blk[$blvl]['padding_bottom']);
            $brbgBR_H = max(0, $brBR_H - $this->blk[$blvl]['border_right']['w'] - $this->blk[$blvl]['padding_right']);
            $brbgBR_V = max(0, $brBR_V - $this->blk[$blvl]['border_bottom']['w'] - $this->blk[$blvl]['padding_bottom']);
            $bgx0 += $this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['padding_left'];
            $bgx1 -= $this->blk[$blvl]['border_right']['w'] + $this->blk[$blvl]['padding_right'];
            if (($this->blk[$blvl]['border_top']['w'] || $this->blk[$blvl]['padding_top']) && $divider != 'pagetop' && !$continuingpage) {
                $bgy0 += $this->blk[$blvl]['border_top']['w'] + $this->blk[$blvl]['padding_top'];
            }
            if (($this->blk[$blvl]['border_bottom']['w'] || $this->blk[$blvl]['padding_bottom']) && $blockstate != 1 && $divider != 'pagebottom') {
                $bgy1 -= $this->blk[$blvl]['border_bottom']['w'] + $this->blk[$blvl]['padding_bottom'];
            }
        } else {
            $brbgTL_H = $brTL_H;
            $brbgTL_V = $brTL_V;
            $brbgTR_H = $brTR_H;
            $brbgTR_V = $brTR_V;
            $brbgBL_H = $brBL_H;
            $brbgBL_V = $brBL_V;
            $brbgBR_H = $brBR_H;
            $brbgBR_V = $brBR_V;
        }

        // Set clipping path
        $s = ' q 0 w '; // Line width=0
        $s .= sprintf('%.3F %.3F m ', ($bgx0 + $brbgTL_H ) * Mpdf::SCALE, ($this->h - $bgy0) * Mpdf::SCALE); // start point TL before the arc
        /* -- BORDER-RADIUS -- */
        if ($brbgTL_H || $brbgTL_V) {
            $s .= $this->_EllipseArc($bgx0 + $brbgTL_H, $bgy0 + $brbgTL_V, $brbgTL_H, $brbgTL_V, 2); // segment 2 TL
        }
        /* -- END BORDER-RADIUS -- */
        $s .= sprintf('%.3F %.3F l ', ($bgx0) * Mpdf::SCALE, ($this->h - ($bgy1 - $brbgBL_V )) * Mpdf::SCALE); // line to BL
        /* -- BORDER-RADIUS -- */
        if ($brbgBL_H || $brbgBL_V) {
            $s .= $this->_EllipseArc($bgx0 + $brbgBL_H, $bgy1 - $brbgBL_V, $brbgBL_H, $brbgBL_V, 3); // segment 3 BL
        }
        /* -- END BORDER-RADIUS -- */
        $s .= sprintf('%.3F %.3F l ', ($bgx1 - $brbgBR_H ) * Mpdf::SCALE, ($this->h - ($bgy1)) * Mpdf::SCALE); // line to BR
        /* -- BORDER-RADIUS -- */
        if ($brbgBR_H || $brbgBR_V) {
            $s .= $this->_EllipseArc($bgx1 - $brbgBR_H, $bgy1 - $brbgBR_V, $brbgBR_H, $brbgBR_V, 4); // segment 4 BR
        }
        /* -- END BORDER-RADIUS -- */
        $s .= sprintf('%.3F %.3F l ', ($bgx1) * Mpdf::SCALE, ($this->h - ($bgy0 + $brbgTR_V)) * Mpdf::SCALE); // line to TR
        /* -- BORDER-RADIUS -- */
        if ($brbgTR_H || $brbgTR_V) {
            $s .= $this->_EllipseArc($bgx1 - $brbgTR_H, $bgy0 + $brbgTR_V, $brbgTR_H, $brbgTR_V, 1); // segment 1 TR
        }
        /* -- END BORDER-RADIUS -- */
        $s .= sprintf('%.3F %.3F l ', ($bgx0 + $brbgTL_H ) * Mpdf::SCALE, ($this->h - $bgy0) * Mpdf::SCALE); // line to TL
        // Box Shadow
        $shadow = '';
        if (isset($this->blk[$blvl]['box_shadow']) && $this->blk[$blvl]['box_shadow'] && $h > 0) {
            foreach ($this->blk[$blvl]['box_shadow'] as $sh) {
                // Colors
                if ($sh['col']{0} == 1) {
                    $colspace = 'Gray';
                    if ($sh['col']{2} == 1) {
                        $col1 = '1' . $sh['col'][1] . '1' . $sh['col'][3];
                    } else {
                        $col1 = '1' . $sh['col'][1] . '1' . chr(100);
                    }
                    $col2 = '1' . $sh['col'][1] . '1' . chr(0);
                } elseif ($sh['col']{0} == 4) { // CMYK
                    $colspace = 'CMYK';
                    $col1 = '6' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . $sh['col'][4] . chr(100);
                    $col2 = '6' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . $sh['col'][4] . chr(0);
                } elseif ($sh['col']{0} == 5) { // RGBa
                    $colspace = 'RGB';
                    $col1 = '5' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . $sh['col'][4];
                    $col2 = '5' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . chr(0);
                } elseif ($sh['col']{0} == 6) { // CMYKa
                    $colspace = 'CMYK';
                    $col1 = '6' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . $sh['col'][4] . $sh['col'][5];
                    $col2 = '6' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . $sh['col'][4] . chr(0);
                } else {
                    $colspace = 'RGB';
                    $col1 = '5' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . chr(100);
                    $col2 = '5' . $sh['col'][1] . $sh['col'][2] . $sh['col'][3] . chr(0);
                }

                // Use clipping path as set above (and rectangle around page) to clip area outside box
                $shadow .= $s; // Use the clipping path with W*
                $shadow .= sprintf('0 %.3F m %.3F %.3F l ', $this->h * Mpdf::SCALE, $this->w * Mpdf::SCALE, $this->h * Mpdf::SCALE);
                $shadow .= sprintf('%.3F 0 l 0 0 l 0 %.3F l ', $this->w * Mpdf::SCALE, $this->h * Mpdf::SCALE);
                $shadow .= 'W n' . "\n";

                $sh['blur'] = abs($sh['blur']); // cannot have negative blur value
                // Ensure spread/blur do not make effective shadow width/height < 0
                // Could do more complex things but this just adjusts spread value
                if (-$sh['spread'] + $sh['blur'] / 2 > min($w / 2, $h / 2)) {
                    $sh['spread'] = $sh['blur'] / 2 - min($w / 2, $h / 2) + 0.01;
                }
                // Shadow Offset
                if ($sh['x'] || $sh['y']) {
                    $shadow .= sprintf(' q 1 0 0 1 %.4F %.4F cm', $sh['x'] * Mpdf::SCALE, -$sh['y'] * Mpdf::SCALE) . "\n";
                }

                // Set path for INNER shadow
                $shadow .= ' q 0 w ';
                $shadow .= $this->SetFColor($col1, true) . "\n";
                if ($col1{0} == 5 && ord($col1{4}) < 100) { // RGBa
                    $shadow .= $this->SetAlpha(ord($col1{4}) / 100, 'Normal', true, 'F') . "\n";
                } elseif ($col1{0} == 6 && ord($col1{5}) < 100) { // CMYKa
                    $shadow .= $this->SetAlpha(ord($col1{5}) / 100, 'Normal', true, 'F') . "\n";
                } elseif ($col1{0} == 1 && $col1{2} == 1 && ord($col1{3}) < 100) { // Gray
                    $shadow .= $this->SetAlpha(ord($col1{3}) / 100, 'Normal', true, 'F') . "\n";
                }

                // Blur edges
                $mag = 0.551784; // Bezier Control magic number for 4-part spline for circle/ellipse
                $mag2 = 0.551784; // Bezier Control magic number to fill in edge of blurred rectangle
                $d1 = $sh['spread'] + $sh['blur'] / 2;
                $d2 = $sh['spread'] - $sh['blur'] / 2;
                $bl = $sh['blur'];
                $x00 = $x0 - $d1;
                $y00 = $y0 - $d1;
                $w00 = $w + $d1 * 2;
                $h00 = $h + $d1 * 2;

                // If any border-radius is greater width-negative spread(inner edge), ignore radii for shadow or screws up
                $flatten = false;
                if (max($brbgTR_H, $brbgTL_H, $brbgBR_H, $brbgBL_H) >= $w + $d2) {
                    $flatten = true;
                }
                if (max($brbgTR_V, $brbgTL_V, $brbgBR_V, $brbgBL_V) >= $h + $d2) {
                    $flatten = true;
                }


                // TOP RIGHT corner
                $p1x = $x00 + $w00 - $d1 - $brbgTR_H;
                $p1c2x = $p1x + ($d2 + $brbgTR_H) * $mag;
                $p1y = $y00 + $bl;
                $p2x = $x00 + $w00 - $d1 - $brbgTR_H;
                $p2c2x = $p2x + ($d1 + $brbgTR_H) * $mag;
                $p2y = $y00;
                $p2c1y = $p2y + $bl / 2;
                $p3x = $x00 + $w00;
                $p3c2x = $p3x - $bl / 2;
                $p3y = $y00 + $d1 + $brbgTR_V;
                $p3c1y = $p3y - ($d1 + $brbgTR_V) * $mag;
                $p4x = $x00 + $w00 - $bl;
                $p4y = $y00 + $d1 + $brbgTR_V;
                $p4c2y = $p4y - ($d2 + $brbgTR_V) * $mag;
                if (-$d2 > min($brbgTR_H, $brbgTR_V) || $flatten) {
                    $p1x = $x00 + $w00 - $bl;
                    $p1c2x = $p1x;
                    $p2x = $x00 + $w00 - $bl;
                    $p2c2x = $p2x + $bl * $mag2;
                    $p3y = $y00 + $bl;
                    $p3c1y = $p3y - $bl * $mag2;
                    $p4y = $y00 + $bl;
                    $p4c2y = $p4y;
                }

                $shadow .= sprintf('%.3F %.3F m ', ($p1x ) * Mpdf::SCALE, ($this->h - ($p1y )) * Mpdf::SCALE);
                $shadow .= sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', ($p1c2x) * Mpdf::SCALE, ($this->h - ($p1y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4c2y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE);
                $patch_array[0]['f'] = 0;
                $patch_array[0]['points'] = [$p1x, $p1y, $p1x, $p1y,
                    $p2x, $p2c1y, $p2x, $p2y, $p2c2x, $p2y,
                    $p3x, $p3c1y, $p3x, $p3y, $p3c2x, $p3y,
                    $p4x, $p4y, $p4x, $p4y, $p4x, $p4c2y,
                    $p1c2x, $p1y];
                $patch_array[0]['colors'] = [$col1, $col2, $col2, $col1];


                // RIGHT
                $p1x = $x00 + $w00; // control point only matches p3 preceding
                $p1y = $y00 + $d1 + $brbgTR_V;
                $p2x = $x00 + $w00 - $bl; // control point only matches p4 preceding
                $p2y = $y00 + $d1 + $brbgTR_V;
                $p3x = $x00 + $w00 - $bl;
                $p3y = $y00 + $h00 - $d1 - $brbgBR_V;
                $p4x = $x00 + $w00;
                $p4c1x = $p4x - $bl / 2;
                $p4y = $y00 + $h00 - $d1 - $brbgBR_V;
                if (-$d2 > min($brbgTR_H, $brbgTR_V) || $flatten) {
                    $p1y = $y00 + $bl;
                    $p2y = $y00 + $bl;
                }
                if (-$d2 > min($brbgBR_H, $brbgBR_V) || $flatten) {
                    $p3y = $y00 + $h00 - $bl;
                    $p4y = $y00 + $h00 - $bl;
                }

                $shadow .= sprintf('%.3F %.3F l ', ($p3x ) * Mpdf::SCALE, ($this->h - ($p3y )) * Mpdf::SCALE);
                $patch_array[1]['f'] = 2;
                $patch_array[1]['points'] = [$p2x, $p2y,
                    $p3x, $p3y, $p3x, $p3y, $p3x, $p3y,
                    $p4c1x, $p4y, $p4x, $p4y, $p4x, $p4y,
                    $p1x, $p1y];
                $patch_array[1]['colors'] = [$col1, $col2];


                // BOTTOM RIGHT corner
                $p1x = $x00 + $w00 - $bl;  // control points only matches p3 preceding
                $p1y = $y00 + $h00 - $d1 - $brbgBR_V;
                $p1c2y = $p1y + ($d2 + $brbgBR_V) * $mag;
                $p2x = $x00 + $w00;     // control point only matches p4 preceding
                $p2y = $y00 + $h00 - $d1 - $brbgBR_V;
                $p2c2y = $p2y + ($d1 + $brbgBR_V) * $mag;
                $p3x = $x00 + $w00 - $d1 - $brbgBR_H;
                $p3c1x = $p3x + ($d1 + $brbgBR_H) * $mag;
                $p3y = $y00 + $h00;
                $p3c2y = $p3y - $bl / 2;
                $p4x = $x00 + $w00 - $d1 - $brbgBR_H;
                $p4c2x = $p4x + ($d2 + $brbgBR_H) * $mag;
                $p4y = $y00 + $h00 - $bl;

                if (-$d2 > min($brbgBR_H, $brbgBR_V) || $flatten) {
                    $p1y = $y00 + $h00 - $bl;
                    $p1c2y = $p1y;
                    $p2y = $y00 + $h00 - $bl;
                    $p2c2y = $p2y + $bl * $mag2;
                    $p3x = $x00 + $w00 - $bl;
                    $p3c1x = $p3x + $bl * $mag2;
                    $p4x = $x00 + $w00 - $bl;
                    $p4c2x = $p4x;
                }

                $shadow .= sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', ($p1x) * Mpdf::SCALE, ($this->h - ($p1c2y)) * Mpdf::SCALE, ($p4c2x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE);
                $patch_array[2]['f'] = 2;
                $patch_array[2]['points'] = [$p2x, $p2c2y,
                    $p3c1x, $p3y, $p3x, $p3y, $p3x, $p3c2y,
                    $p4x, $p4y, $p4x, $p4y, $p4c2x, $p4y,
                    $p1x, $p1c2y];
                $patch_array[2]['colors'] = [$col2, $col1];



                // BOTTOM
                $p1x = $x00 + $w00 - $d1 - $brbgBR_H; // control point only matches p3 preceding
                $p1y = $y00 + $h00;
                $p2x = $x00 + $w00 - $d1 - $brbgBR_H; // control point only matches p4 preceding
                $p2y = $y00 + $h00 - $bl;
                $p3x = $x00 + $d1 + $brbgBL_H;
                $p3y = $y00 + $h00 - $bl;
                $p4x = $x00 + $d1 + $brbgBL_H;
                $p4y = $y00 + $h00;
                $p4c1y = $p4y - $bl / 2;

                if (-$d2 > min($brbgBR_H, $brbgBR_V) || $flatten) {
                    $p1x = $x00 + $w00 - $bl;
                    $p2x = $x00 + $w00 - $bl;
                }
                if (-$d2 > min($brbgBL_H, $brbgBL_V) || $flatten) {
                    $p3x = $x00 + $bl;
                    $p4x = $x00 + $bl;
                }

                $shadow .= sprintf('%.3F %.3F l ', ($p3x ) * Mpdf::SCALE, ($this->h - ($p3y )) * Mpdf::SCALE);
                $patch_array[3]['f'] = 2;
                $patch_array[3]['points'] = [$p2x, $p2y,
                    $p3x, $p3y, $p3x, $p3y, $p3x, $p3y,
                    $p4x, $p4c1y, $p4x, $p4y, $p4x, $p4y,
                    $p1x, $p1y];
                $patch_array[3]['colors'] = [$col1, $col2];

                // BOTTOM LEFT corner
                $p1x = $x00 + $d1 + $brbgBL_H;
                $p1c2x = $p1x - ($d2 + $brbgBL_H) * $mag; // control points only matches p3 preceding
                $p1y = $y00 + $h00 - $bl;
                $p2x = $x00 + $d1 + $brbgBL_H;
                $p2c2x = $p2x - ($d1 + $brbgBL_H) * $mag; // control point only matches p4 preceding
                $p2y = $y00 + $h00;
                $p3x = $x00;
                $p3c2x = $p3x + $bl / 2;
                $p3y = $y00 + $h00 - $d1 - $brbgBL_V;
                $p3c1y = $p3y + ($d1 + $brbgBL_V) * $mag;
                $p4x = $x00 + $bl;
                $p4y = $y00 + $h00 - $d1 - $brbgBL_V;
                $p4c2y = $p4y + ($d2 + $brbgBL_V) * $mag;
                if (-$d2 > min($brbgBL_H, $brbgBL_V) || $flatten) {
                    $p1x = $x00 + $bl;
                    $p1c2x = $p1x;
                    $p2x = $x00 + $bl;
                    $p2c2x = $p2x - $bl * $mag2;
                    $p3y = $y00 + $h00 - $bl;
                    $p3c1y = $p3y + $bl * $mag2;
                    $p4y = $y00 + $h00 - $bl;
                    $p4c2y = $p4y;
                }

                $shadow .= sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', ($p1c2x) * Mpdf::SCALE, ($this->h - ($p1y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4c2y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE);
                $patch_array[4]['f'] = 2;
                $patch_array[4]['points'] = [$p2c2x, $p2y,
                    $p3x, $p3c1y, $p3x, $p3y, $p3c2x, $p3y,
                    $p4x, $p4y, $p4x, $p4y, $p4x, $p4c2y,
                    $p1c2x, $p1y];
                $patch_array[4]['colors'] = [$col2, $col1];


                // LEFT - joins on the right (C3-C4 of previous): f = 2
                $p1x = $x00; // control point only matches p3 preceding
                $p1y = $y00 + $h00 - $d1 - $brbgBL_V;
                $p2x = $x00 + $bl; // control point only matches p4 preceding
                $p2y = $y00 + $h00 - $d1 - $brbgBL_V;
                $p3x = $x00 + $bl;
                $p3y = $y00 + $d1 + $brbgTL_V;
                $p4x = $x00;
                $p4c1x = $p4x + $bl / 2;
                $p4y = $y00 + $d1 + $brbgTL_V;
                if (-$d2 > min($brbgBL_H, $brbgBL_V) || $flatten) {
                    $p1y = $y00 + $h00 - $bl;
                    $p2y = $y00 + $h00 - $bl;
                }
                if (-$d2 > min($brbgTL_H, $brbgTL_V) || $flatten) {
                    $p3y = $y00 + $bl;
                    $p4y = $y00 + $bl;
                }

                $shadow .= sprintf('%.3F %.3F l ', ($p3x ) * Mpdf::SCALE, ($this->h - ($p3y )) * Mpdf::SCALE);
                $patch_array[5]['f'] = 2;
                $patch_array[5]['points'] = [$p2x, $p2y,
                    $p3x, $p3y, $p3x, $p3y, $p3x, $p3y,
                    $p4c1x, $p4y, $p4x, $p4y, $p4x, $p4y,
                    $p1x, $p1y];
                $patch_array[5]['colors'] = [$col1, $col2];

                // TOP LEFT corner
                $p1x = $x00 + $bl;  // control points only matches p3 preceding
                $p1y = $y00 + $d1 + $brbgTL_V;
                $p1c2y = $p1y - ($d2 + $brbgTL_V) * $mag;
                $p2x = $x00;   // control point only matches p4 preceding
                $p2y = $y00 + $d1 + $brbgTL_V;
                $p2c2y = $p2y - ($d1 + $brbgTL_V) * $mag;
                $p3x = $x00 + $d1 + $brbgTL_H;
                $p3c1x = $p3x - ($d1 + $brbgTL_H) * $mag;
                $p3y = $y00;
                $p3c2y = $p3y + $bl / 2;
                $p4x = $x00 + $d1 + $brbgTL_H;
                $p4c2x = $p4x - ($d2 + $brbgTL_H) * $mag;
                $p4y = $y00 + $bl;

                if (-$d2 > min($brbgTL_H, $brbgTL_V) || $flatten) {
                    $p1y = $y00 + $bl;
                    $p1c2y = $p1y;
                    $p2y = $y00 + $bl;
                    $p2c2y = $p2y - $bl * $mag2;
                    $p3x = $x00 + $bl;
                    $p3c1x = $p3x - $bl * $mag2;
                    $p4x = $x00 + $bl;
                    $p4c2x = $p4x;
                }

                $shadow .= sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', ($p1x) * Mpdf::SCALE, ($this->h - ($p1c2y)) * Mpdf::SCALE, ($p4c2x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE, ($p4x) * Mpdf::SCALE, ($this->h - ($p4y)) * Mpdf::SCALE);
                $patch_array[6]['f'] = 2;
                $patch_array[6]['points'] = [$p2x, $p2c2y,
                    $p3c1x, $p3y, $p3x, $p3y, $p3x, $p3c2y,
                    $p4x, $p4y, $p4x, $p4y, $p4c2x, $p4y,
                    $p1x, $p1c2y];
                $patch_array[6]['colors'] = [$col2, $col1];


                // TOP - joins on the right (C3-C4 of previous): f = 2
                $p1x = $x00 + $d1 + $brbgTL_H; // control point only matches p3 preceding
                $p1y = $y00;
                $p2x = $x00 + $d1 + $brbgTL_H; // control point only matches p4 preceding
                $p2y = $y00 + $bl;
                $p3x = $x00 + $w00 - $d1 - $brbgTR_H;
                $p3y = $y00 + $bl;
                $p4x = $x00 + $w00 - $d1 - $brbgTR_H;
                $p4y = $y00;
                $p4c1y = $p4y + $bl / 2;
                if (-$d2 > min($brbgTL_H, $brbgTL_V) || $flatten) {
                    $p1x = $x00 + $bl;
                    $p2x = $x00 + $bl;
                }
                if (-$d2 > min($brbgTR_H, $brbgTR_V) || $flatten) {
                    $p3x = $x00 + $w00 - $bl;
                    $p4x = $x00 + $w00 - $bl;
                }

                $shadow .= sprintf('%.3F %.3F l ', ($p3x ) * Mpdf::SCALE, ($this->h - ($p3y )) * Mpdf::SCALE);
                $patch_array[7]['f'] = 2;
                $patch_array[7]['points'] = [$p2x, $p2y,
                    $p3x, $p3y, $p3x, $p3y, $p3x, $p3y,
                    $p4x, $p4c1y, $p4x, $p4y, $p4x, $p4y,
                    $p1x, $p1y];
                $patch_array[7]['colors'] = [$col1, $col2];

                $shadow .= ' h f Q ' . "\n"; // Close path and Fill the inner solid shadow

                if ($bl) {
                    $shadow .= $this->gradient->CoonsPatchMesh($x00, $y00, $w00, $h00, $patch_array, $x00, $x00 + $w00, $y00, $y00 + $h00, $colspace, true);
                }

                if ($sh['x'] || $sh['y']) {
                    $shadow .= ' Q' . "\n";  // Shadow Offset
                }
                $shadow .= ' Q' . "\n"; // Ends path no-op & Sets the clipping path
            }
        }

        $s .= ' W n '; // Ends path no-op & Sets the clipping path

        if ($this->blk[$blvl]['bgcolor']) {
            $this->pageBackgrounds[$blvl][] = ['x' => $x0, 'y' => $y0, 'w' => $w, 'h' => $h, 'col' => $this->blk[$blvl]['bgcolorarray'], 'clippath' => $s, 'visibility' => $this->visibility, 'shadow' => $shadow, 'z-index' => $this->current_layer];
        } elseif ($shadow) {
            $this->pageBackgrounds[$blvl][] = ['shadowonly' => true, 'col' => '', 'clippath' => '', 'visibility' => $this->visibility, 'shadow' => $shadow, 'z-index' => $this->current_layer];
        }

        /* -- BACKGROUNDS -- */
        if (isset($this->blk[$blvl]['gradient'])) {
            $g = $this->gradient->parseBackgroundGradient($this->blk[$blvl]['gradient']);
            if ($g) {
                $gx = $x0;
                $gy = $y0;
                $this->pageBackgrounds[$blvl][] = ['gradient' => true, 'x' => $gx, 'y' => $gy, 'w' => $w, 'h' => $h, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => $s, 'visibility' => $this->visibility, 'z-index' => $this->current_layer];
            }
        }
        if (isset($this->blk[$blvl]['background-image'])) {
            if (isset($this->blk[$blvl]['background-image']['gradient']) && $this->blk[$blvl]['background-image']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $this->blk[$blvl]['background-image']['gradient'])) {
                $g = $this->gradient->parseMozGradient($this->blk[$blvl]['background-image']['gradient']);
                if ($g) {
                    $gx = $x0;
                    $gy = $y0;
                    // origin specifies the background-positioning-area (bpa)
                    if ($this->blk[$blvl]['background-image']['origin'] == 'padding-box') {
                        $gx += $this->blk[$blvl]['border_left']['w'];
                        $w -= ($this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['border_right']['w']);
                        if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
                            $gy += $this->blk[$blvl]['border_top']['w'];
                        }
                        if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
                            $gy1 = $y1 - $this->blk[$blvl]['border_bottom']['w'];
                        } else {
                            $gy1 = $y1;
                        }
                        $h = $gy1 - $gy;
                    } elseif ($this->blk[$blvl]['background-image']['origin'] == 'content-box') {
                        $gx += $this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['padding_left'];
                        $w -= ($this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['padding_left'] + $this->blk[$blvl]['border_right']['w'] + $this->blk[$blvl]['padding_right']);
                        if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
                            $gy += $this->blk[$blvl]['border_top']['w'] + $this->blk[$blvl]['padding_top'];
                        }
                        if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
                            $gy1 = $y1 - ($this->blk[$blvl]['border_bottom']['w'] + $this->blk[$blvl]['padding_bottom']);
                        } else {
                            $gy1 = $y1 - $this->blk[$blvl]['padding_bottom'];
                        }
                        $h = $gy1 - $gy;
                    }

                    if (isset($this->blk[$blvl]['background-image']['size']['w']) && $this->blk[$blvl]['background-image']['size']['w']) {
                        $size = $this->blk[$blvl]['background-image']['size'];
                        if ($size['w'] != 'contain' && $size['w'] != 'cover') {
                            if (stristr($size['w'], '%')) {
                                $size['w'] = (float) $size['w'];
                                $size['w'] /= 100;
                                $w *= $size['w'];
                            } elseif ($size['w'] != 'auto') {
                                $w = $size['w'];
                            }
                            if (stristr($size['h'], '%')) {
                                $size['h'] = (float) $size['h'];
                                $size['h'] /= 100;
                                $h *= $size['h'];
                            } elseif ($size['h'] != 'auto') {
                                $h = $size['h'];
                            }
                        }
                    }
                    $this->pageBackgrounds[$blvl][] = ['gradient' => true, 'x' => $gx, 'y' => $gy, 'w' => $w, 'h' => $h, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => $s, 'visibility' => $this->visibility, 'z-index' => $this->current_layer];
                }
            } else {
                $image_id = $this->blk[$blvl]['background-image']['image_id'];
                $orig_w = $this->blk[$blvl]['background-image']['orig_w'];
                $orig_h = $this->blk[$blvl]['background-image']['orig_h'];
                $x_pos = $this->blk[$blvl]['background-image']['x_pos'];
                $y_pos = $this->blk[$blvl]['background-image']['y_pos'];
                $x_repeat = $this->blk[$blvl]['background-image']['x_repeat'];
                $y_repeat = $this->blk[$blvl]['background-image']['y_repeat'];
                $resize = $this->blk[$blvl]['background-image']['resize'];
                $opacity = $this->blk[$blvl]['background-image']['opacity'];
                $itype = $this->blk[$blvl]['background-image']['itype'];
                $size = $this->blk[$blvl]['background-image']['size'];
                // origin specifies the background-positioning-area (bpa)
                $bpa = ['x' => $x0, 'y' => $y0, 'w' => $w, 'h' => $h];
                if ($this->blk[$blvl]['background-image']['origin'] == 'padding-box') {
                    $bpa['x'] = $x0 + $this->blk[$blvl]['border_left']['w'];
                    $bpa['w'] = $w - ($this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['border_right']['w']);
                    if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
                        $bpa['y'] = $y0 + $this->blk[$blvl]['border_top']['w'];
                    } else {
                        $bpa['y'] = $y0;
                    }
                    if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
                        $bpay = $y1 - $this->blk[$blvl]['border_bottom']['w'];
                    } else {
                        $bpay = $y1;
                    }
                    $bpa['h'] = $bpay - $bpa['y'];
                } elseif ($this->blk[$blvl]['background-image']['origin'] == 'content-box') {
                    $bpa['x'] = $x0 + $this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['padding_left'];
                    $bpa['w'] = $w - ($this->blk[$blvl]['border_left']['w'] + $this->blk[$blvl]['padding_left'] + $this->blk[$blvl]['border_right']['w'] + $this->blk[$blvl]['padding_right']);
                    if ($this->blk[$blvl]['border_top'] && $divider != 'pagetop' && !$continuingpage) {
                        $bpa['y'] = $y0 + $this->blk[$blvl]['border_top']['w'] + $this->blk[$blvl]['padding_top'];
                    } else {
                        $bpa['y'] = $y0 + $this->blk[$blvl]['padding_top'];
                    }
                    if ($this->blk[$blvl]['border_bottom'] && $blockstate != 1 && $divider != 'pagebottom') {
                        $bpay = $y1 - ($this->blk[$blvl]['border_bottom']['w'] + $this->blk[$blvl]['padding_bottom']);
                    } else {
                        $bpay = $y1 - $this->blk[$blvl]['padding_bottom'];
                    }
                    $bpa['h'] = $bpay - $bpa['y'];
                }
                $this->pageBackgrounds[$blvl][] = ['x' => $x0, 'y' => $y0, 'w' => $w, 'h' => $h, 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => $s, 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype, 'visibility' => $this->visibility, 'z-index' => $this->current_layer, 'size' => $size, 'bpa' => $bpa];
            }
        }
        /* -- END BACKGROUNDS -- */

        // Float DIV
        $this->blk[$blvl]['bb_painted'][$this->page] = true;
    }

    public function PaintDivLnBorder($state = 0, $blvl = 0, $h = 0)
    {
        // $state = 0 normal; 1 top; 2 bottom; 3 top and bottom
        $this->ColDetails[$this->CurrCol]['bottom_margin'] = $this->y + $h;

        $save_y = $this->y;

        $w = $this->blk[$blvl]['width'];
        $x0 = $this->x;    // left
        $y0 = $this->y;    // top
        $x1 = $this->x + $w;   // bottom
        $y1 = $this->y + $h;   // bottom

        if ($this->blk[$blvl]['border_top'] && ($state == 1 || $state == 3)) {
            $tbd = $this->blk[$blvl]['border_top'];
            if (isset($tbd['s']) && $tbd['s']) {
                $this->_setBorderLine($tbd);
                $this->y = $y0 + ($tbd['w'] / 2);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->_setDashBorder($tbd['style'], '', $continuingpage, 'T');
                    $this->Line($x0 + ($tbd['w'] / 2), $this->y, $x0 + $w - ($tbd['w'] / 2), $this->y);
                } else {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                    $this->Line($x0, $this->y, $x0 + $w, $this->y);
                }
                $this->y += $tbd['w'];
                // Reset Corners and Dash off
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        if ($this->blk[$blvl]['border_left']) {
            $tbd = $this->blk[$blvl]['border_left'];
            if (isset($tbd['s']) && $tbd['s']) {
                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->y = $y0 + ($tbd['w'] / 2);
                    $this->_setDashBorder($tbd['style'], '', $continuingpage, 'L');
                    $this->Line($x0 + ($tbd['w'] / 2), $this->y, $x0 + ($tbd['w'] / 2), $y0 + $h - ($tbd['w'] / 2));
                } else {
                    $this->y = $y0;
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                    $this->Line($x0 + ($tbd['w'] / 2), $this->y, $x0 + ($tbd['w'] / 2), $y0 + $h);
                }
                $this->y += $tbd['w'];
                // Reset Corners and Dash off
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        if ($this->blk[$blvl]['border_right']) {
            $tbd = $this->blk[$blvl]['border_right'];
            if (isset($tbd['s']) && $tbd['s']) {
                $this->_setBorderLine($tbd);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->y = $y0 + ($tbd['w'] / 2);
                    $this->_setDashBorder($tbd['style'], '', $continuingpage, 'R');
                    $this->Line($x0 + $w - ($tbd['w'] / 2), $this->y, $x0 + $w - ($tbd['w'] / 2), $y0 + $h - ($tbd['w'] / 2));
                } else {
                    $this->y = $y0;
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                    $this->Line($x0 + $w - ($tbd['w'] / 2), $this->y, $x0 + $w - ($tbd['w'] / 2), $y0 + $h);
                }
                $this->y += $tbd['w'];
                // Reset Corners and Dash off
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        if ($this->blk[$blvl]['border_bottom'] && $state > 1) {
            $tbd = $this->blk[$blvl]['border_bottom'];
            if (isset($tbd['s']) && $tbd['s']) {
                $this->_setBorderLine($tbd);
                $this->y = $y0 + $h - ($tbd['w'] / 2);
                if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                    $this->_setDashBorder($tbd['style'], '', $continuingpage, 'B');
                    $this->Line($x0 + ($tbd['w'] / 2), $this->y, $x0 + $w - ($tbd['w'] / 2), $this->y);
                } else {
                    $this->SetLineJoin(0);
                    $this->SetLineCap(0);
                    $this->Line($x0, $this->y, $x0 + $w, $this->y);
                }
                $this->y += $tbd['w'];
                // Reset Corners and Dash off
                $this->SetLineJoin(2);
                $this->SetLineCap(2);
                $this->SetDash();
            }
        }
        $this->SetDash();
        $this->y = $save_y;
    }

    public function PaintImgBorder($objattr, $is_table)
    {
        // Borders are disabled in columns - messes up the repositioning in printcolumnbuffer
        if ($this->ColActive) {
            return;
        } // *COLUMNS*
        if ($is_table) {
            $k = $this->shrin_k;
        } else {
            $k = 1;
        }

        $height = (isset($objattr['BORDER-HEIGHT']) ? $objattr['BORDER-HEIGHT'] : 0);
        $width  = (isset($objattr['BORDER-WIDTH']) ? $objattr['BORDER-WIDTH'] : 0);
        $left   = (isset($objattr['BORDER-X']) ? $objattr['BORDER-X'] : 0);
        $top    = (isset($objattr['BORDER-Y']) ? $objattr['BORDER-Y'] : 0);

        $right  = $x0 + $width;
        $bottom = $y0 + $height;

        $border_settings = [
            [ 'border_top',    'T', $left,  $top,    $right, $top    ],
            [ 'border_left',   'L', $left,  $top,    $left,  $bottom ],
            [ 'border_right',  'R', $right, $top,    $right, $bottom ],
            [ 'border_bottom', 'B', $left,  $bottom, $right, $bottom ],
        ];

        foreach($border_settings as $bs){
            $css_side = array_shift($bs);
            $side     = array_shift($bs);
            $x1       = array_shift($bs);
            $y1       = array_shift($bs);
            $x2       = array_shift($bs);
            $y2       = array_shift($bs);

            if ($objattr[$css_side]) {
                $tbd = $objattr[$css_side];
                if (!empty($tbd['s'])) {
                    $this->_setBorderLine($tbd, $k);
                    if ($tbd['style'] == 'dotted' || $tbd['style'] == 'dashed') {
                        $this->_setDashBorder($tbd['style'], '', '', $side);
                    }
                    $this->Line($x1, $y1, $x2, $y2);
                    // Reset Corners and Dash off
                    $this->SetLineJoin(2);
                    $this->SetLineCap(2);
                    $this->SetDash();
                }
            }
        }

        $this->SetDash();
        $this->SetAlpha(1);
    }

    /* -- END HTML-CSS -- */

    public function Reset()
    {
        $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
        $this->SetAlpha(1);
        $this->colorarray = '';

        $this->spanbgcolorarray = '';
        $this->spanbgcolor = false;
        $this->spanborder = false;
        $this->spanborddet = [];

        $this->ResetStyles();

        $this->HREF = '';
        $this->textparam = [];
        $this->SetTextOutline();

        $this->textvar = 0x00; // mPDF 5.7.1
        $this->OTLtags = [];
        $this->textshadow = '';

        $this->currentLang = $this->default_lang;  // mPDF 6
        $this->RestrictUnicodeFonts($this->default_available_fonts); // mPDF 6
        $this->SetFont($this->default_font, '', 0, false);
        $this->SetFontSize($this->default_font_size, false);

        $this->currentfontfamily = '';
        $this->currentfontsize = '';
        $this->currentfontstyle = '';

        /* -- TABLES -- */
        if ($this->tableLevel && isset($this->table[1][1]['cellLineHeight'])) {
            $this->SetLineHeight('', $this->table[1][1]['cellLineHeight']); // *TABLES*
        } else {        /* -- END TABLES -- */
            if (isset($this->blk[$this->blklvl]['line_height']) && $this->blk[$this->blklvl]['line_height']) {
                $this->SetLineHeight('', $this->blk[$this->blklvl]['line_height']); // sets default line height
            }
        }

        $this->lSpacingCSS = '';
        $this->wSpacingCSS = '';
        $this->fixedlSpacing = false;
        $this->minwSpacing = 0;
        $this->SetDash(); // restore to no dash
        $this->dash_on = false;
        $this->dotted_on = false;
        $this->divwidth = 0;
        $this->divheight = 0;
        $this->cellTextAlign = '';
        $this->cellLineHeight = '';
        $this->cellLineStackingStrategy = '';
        $this->cellLineStackingShift = '';
        $this->oldy = -1;

        $bodystyle = [];
        if (isset($this->cssManager->CSS['BODY']['FONT-STYLE'])) {
            $bodystyle['FONT-STYLE'] = $this->cssManager->CSS['BODY']['FONT-STYLE'];
        }
        if (isset($this->cssManager->CSS['BODY']['FONT-WEIGHT'])) {
            $bodystyle['FONT-WEIGHT'] = $this->cssManager->CSS['BODY']['FONT-WEIGHT'];
        }
        if (isset($this->cssManager->CSS['BODY']['COLOR'])) {
            $bodystyle['COLOR'] = $this->cssManager->CSS['BODY']['COLOR'];
        }
        if (isset($bodystyle)) {
            $this->setCSS($bodystyle, 'BLOCK', 'BODY');
        }
    }

    /* -- HTML-CSS -- */

    public function ReadMetaTags($html)
    {
        // changes anykey=anyvalue to anykey="anyvalue" (only do this when this happens inside tags)
        $regexp = '/ (\\w+?)=([^\\s>"]+)/si';
        $html = preg_replace($regexp, " \$1=\"\$2\"", $html);
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) {
            $this->SetTitle($m[1]);
        }
        preg_match_all('/<meta [^>]*?(name|content)="([^>]*?)" [^>]*?(name|content)="([^>]*?)".*?>/si', $html, $aux);
        $firstattr = $aux[1];
        $secondattr = $aux[3];
        for ($i = 0; $i < count($aux[0]); $i++) {
            $name = ( strtoupper($firstattr[$i]) == "NAME" ) ? strtoupper($aux[2][$i]) : strtoupper($aux[4][$i]);
            $content = ( strtoupper($firstattr[$i]) == "CONTENT" ) ? $aux[2][$i] : $aux[4][$i];
            switch ($name) {
                case "KEYWORDS":
                    $this->SetKeywords($content);
                    break;
                case "AUTHOR":
                    $this->SetAuthor($content);
                    break;
                case "DESCRIPTION":
                    $this->SetSubject($content);
                    break;
            }
        }
    }

    public function ReadCharset($html)
    {
        // Charset conversion
        if ($this->allow_charset_conversion) {
            if (preg_match('/<head.*charset=([^\'\"\s]*).*<\/head>/si', $html, $m)) {
                if (strtoupper($m[1]) != 'UTF-8') {
                    $this->charset_in = strtoupper($m[1]);
                }
            }
        }
    }

    public function setCSS($arrayaux, $type = '', $tag = '')
    {
    // type= INLINE | BLOCK | TABLECELL // tag= BODY
        if (!is_array($arrayaux)) {
            return; // Removes PHP Warning
        }

        // mPDF 5.7.3  inline text-decoration parameters
        $preceeding_fontkey = $this->FontFamily . $this->FontStyle;
        $preceeding_fontsize = $this->FontSize;
        $spanbordset = false;
        $spanbgset = false;
        // mPDF 6
        $prevlevel = (($this->blklvl == 0) ? 0 : $this->blklvl - 1);

        // Set font size first so that e.g. MARGIN 0.83em works on font size for this element
        if (isset($arrayaux['FONT-SIZE'])) {
            $v = $arrayaux['FONT-SIZE'];
            if (is_numeric($v[0])) {
                if ($type == 'BLOCK' && $this->blklvl > 0 && isset($this->blk[$this->blklvl - 1]['InlineProperties']) && isset($this->blk[$this->blklvl - 1]['InlineProperties']['size'])) {
                    $mmsize = $this->sizeConverter->convert($v, $this->blk[$this->blklvl - 1]['InlineProperties']['size']);
                } elseif ($type == 'TABLECELL') {
                    $mmsize = $this->sizeConverter->convert($v, $this->default_font_size / Mpdf::SCALE);
                } else {
                    $mmsize = $this->sizeConverter->convert($v, $this->FontSize);
                }
                $this->SetFontSize($mmsize * (Mpdf::SCALE), false); // Get size in points (pt)
            } else {
                $v = strtoupper($v);
                if (isset($this->fontsizes[$v])) {
                    $this->SetFontSize($this->fontsizes[$v] * $this->default_font_size, false);
                }
            }
            if ($tag == 'BODY') {
                $this->SetDefaultFontSize($this->FontSizePt);
            }
        }

        // mPDF 6
        if (isset($arrayaux['LANG']) && $arrayaux['LANG']) {
            if ($this->autoLangToFont && !$this->usingCoreFont) {
                if ($arrayaux['LANG'] != $this->default_lang && $arrayaux['LANG'] != 'UTF-8') {
                    list ($coreSuitable, $mpdf_pdf_unifont) = $this->languageToFont->getLanguageOptions($arrayaux['LANG'], $this->useAdobeCJK);
                    if ($mpdf_pdf_unifont) {
                        $arrayaux['FONT-FAMILY'] = $mpdf_pdf_unifont;
                    }
                    if ($tag == 'BODY') {
                        $this->default_lang = $arrayaux['LANG'];
                    }
                }
            }
            $this->currentLang = $arrayaux['LANG'];
        }

        // FOR INLINE and BLOCK OR 'BODY'
        if (isset($arrayaux['FONT-FAMILY'])) {
            $v = $arrayaux['FONT-FAMILY'];
            // If it is a font list, get all font types
            $aux_fontlist = explode(",", $v);
            $found = 0;
            foreach ($aux_fontlist as $f) {
                $fonttype = trim($f);
                $fonttype = preg_replace('/["\']*(.*?)["\']*/', '\\1', $fonttype);
                $fonttype = preg_replace('/ /', '', $fonttype);
                $v = strtolower(trim($fonttype));
                if (isset($this->fonttrans[$v]) && $this->fonttrans[$v]) {
                    $v = $this->fonttrans[$v];
                }
                if ((!$this->onlyCoreFonts && in_array($v, $this->available_unifonts)) ||
                    in_array($v, ['ccourier', 'ctimes', 'chelvetica']) ||
                    ($this->onlyCoreFonts && in_array($v, ['courier', 'times', 'helvetica', 'arial'])) ||
                    in_array($v, ['sjis', 'uhc', 'big5', 'gb'])) {
                    $fonttype = $v;
                    $found = 1;
                    break;
                }
            }
            if (!$found) {
                foreach ($aux_fontlist as $f) {
                    $fonttype = trim($f);
                    $fonttype = preg_replace('/["\']*(.*?)["\']*/', '\\1', $fonttype);
                    $fonttype = preg_replace('/ /', '', $fonttype);
                    $v = strtolower(trim($fonttype));
                    if (isset($this->fonttrans[$v]) && $this->fonttrans[$v]) {
                        $v = $this->fonttrans[$v];
                    }
                    if (in_array($v, $this->sans_fonts) || in_array($v, $this->serif_fonts) || in_array($v, $this->mono_fonts)) {
                        $fonttype = $v;
                        break;
                    }
                }
            }

            if ($tag == 'BODY') {
                $this->SetDefaultFont($fonttype);
            }
            $this->SetFont($fonttype, $this->currentfontstyle, 0, false);
        } else {
            $this->SetFont($this->currentfontfamily, $this->currentfontstyle, 0, false);
        }

        foreach ($arrayaux as $k => $v) {
            if ($type != 'INLINE' && $tag != 'BODY' && $type != 'TABLECELL') {
                switch ($k) {
                    // BORDERS
                    case 'BORDER-TOP':
                        $this->blk[$this->blklvl]['border_top'] = $this->border_details($v);
                        if ($this->blk[$this->blklvl]['border_top']['s']) {
                            $this->blk[$this->blklvl]['border'] = 1;
                        }
                        break;
                    case 'BORDER-BOTTOM':
                        $this->blk[$this->blklvl]['border_bottom'] = $this->border_details($v);
                        if ($this->blk[$this->blklvl]['border_bottom']['s']) {
                            $this->blk[$this->blklvl]['border'] = 1;
                        }
                        break;
                    case 'BORDER-LEFT':
                        $this->blk[$this->blklvl]['border_left'] = $this->border_details($v);
                        if ($this->blk[$this->blklvl]['border_left']['s']) {
                            $this->blk[$this->blklvl]['border'] = 1;
                        }
                        break;
                    case 'BORDER-RIGHT':
                        $this->blk[$this->blklvl]['border_right'] = $this->border_details($v);
                        if ($this->blk[$this->blklvl]['border_right']['s']) {
                            $this->blk[$this->blklvl]['border'] = 1;
                        }
                        break;

                    // PADDING
                    case 'PADDING-TOP':
                        $this->blk[$this->blklvl]['padding_top'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'PADDING-BOTTOM':
                        $this->blk[$this->blklvl]['padding_bottom'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'PADDING-LEFT':
                        if (($tag == 'UL' || $tag == 'OL') && $v == 'auto') {
                            $this->blk[$this->blklvl]['padding_left'] = 'auto';
                            break;
                        }
                        $this->blk[$this->blklvl]['padding_left'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'PADDING-RIGHT':
                        if (($tag == 'UL' || $tag == 'OL') && $v == 'auto') {
                            $this->blk[$this->blklvl]['padding_right'] = 'auto';
                            break;
                        }
                        $this->blk[$this->blklvl]['padding_right'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;

                    // MARGINS
                    case 'MARGIN-TOP':
                        $tmp = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        if (isset($this->blk[$this->blklvl]['lastbottommargin'])) {
                            if ($tmp > $this->blk[$this->blklvl]['lastbottommargin']) {
                                $tmp -= $this->blk[$this->blklvl]['lastbottommargin'];
                            } else {
                                $tmp = 0;
                            }
                        }
                        $this->blk[$this->blklvl]['margin_top'] = $tmp;
                        break;
                    case 'MARGIN-BOTTOM':
                        $this->blk[$this->blklvl]['margin_bottom'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'MARGIN-LEFT':
                        $this->blk[$this->blklvl]['margin_left'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'MARGIN-RIGHT':
                        $this->blk[$this->blklvl]['margin_right'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;

                    /* -- BORDER-RADIUS -- */
                    case 'BORDER-TOP-LEFT-RADIUS-H':
                        $this->blk[$this->blklvl]['border_radius_TL_H'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-TOP-LEFT-RADIUS-V':
                        $this->blk[$this->blklvl]['border_radius_TL_V'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-TOP-RIGHT-RADIUS-H':
                        $this->blk[$this->blklvl]['border_radius_TR_H'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-TOP-RIGHT-RADIUS-V':
                        $this->blk[$this->blklvl]['border_radius_TR_V'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-BOTTOM-LEFT-RADIUS-H':
                        $this->blk[$this->blklvl]['border_radius_BL_H'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-BOTTOM-LEFT-RADIUS-V':
                        $this->blk[$this->blklvl]['border_radius_BL_V'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-BOTTOM-RIGHT-RADIUS-H':
                        $this->blk[$this->blklvl]['border_radius_BR_H'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    case 'BORDER-BOTTOM-RIGHT-RADIUS-V':
                        $this->blk[$this->blklvl]['border_radius_BR_V'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        break;
                    /* -- END BORDER-RADIUS -- */

                    case 'BOX-SHADOW':
                        $bs = $this->cssManager->setCSSboxshadow($v);
                        if ($bs) {
                            $this->blk[$this->blklvl]['box_shadow'] = $bs;
                        }
                        break;

                    case 'BACKGROUND-CLIP':
                        if (strtoupper($v) == 'PADDING-BOX') {
                            $this->blk[$this->blklvl]['background_clip'] = 'padding-box';
                        } elseif (strtoupper($v) == 'CONTENT-BOX') {
                            $this->blk[$this->blklvl]['background_clip'] = 'content-box';
                        }
                        break;

                    case 'PAGE-BREAK-AFTER':
                        if (strtoupper($v) == 'AVOID') {
                            $this->blk[$this->blklvl]['page_break_after_avoid'] = true;
                        } elseif (strtoupper($v) == 'ALWAYS' || strtoupper($v) == 'LEFT' || strtoupper($v) == 'RIGHT') {
                            $this->blk[$this->blklvl]['page_break_after'] = strtoupper($v);
                        }
                        break;

                    // mPDF 6 pagebreaktype
                    case 'BOX-DECORATION-BREAK':
                        if (strtoupper($v) == 'CLONE') {
                            $this->blk[$this->blklvl]['box_decoration_break'] = 'clone';
                        } elseif (strtoupper($v) == 'SLICE') {
                            $this->blk[$this->blklvl]['box_decoration_break'] = 'slice';
                        }
                        break;

                    case 'WIDTH':
                        if (strtoupper($v) != 'AUTO') {
                            $this->blk[$this->blklvl]['css_set_width'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false);
                        }
                        break;

                    // mPDF 6  Lists
                    // LISTS
                    case 'LIST-STYLE-TYPE':
                        $this->blk[$this->blklvl]['list_style_type'] = strtolower($v);
                        break;
                    case 'LIST-STYLE-IMAGE':
                        $this->blk[$this->blklvl]['list_style_image'] = strtolower($v);
                        break;
                    case 'LIST-STYLE-POSITION':
                        $this->blk[$this->blklvl]['list_style_position'] = strtolower($v);
                        break;
                }//end of switch($k)
            }


            if ($type != 'INLINE' && $type != 'TABLECELL') { // All block-level, including BODY tag
                switch ($k) {
                    case 'TEXT-INDENT':
                        // Computed value - to inherit
                        $this->blk[$this->blklvl]['text_indent'] = $this->sizeConverter->convert($v, $this->blk[$prevlevel]['inner_width'], $this->FontSize, false) . 'mm';
                        break;

                    case 'MARGIN-COLLAPSE': // Custom tag to collapse margins at top and bottom of page
                        if (strtoupper($v) == 'COLLAPSE') {
                            $this->blk[$this->blklvl]['margin_collapse'] = true;
                        }
                        break;

                    case 'LINE-HEIGHT':
                        $this->blk[$this->blklvl]['line_height'] = $this->fixLineheight($v);
                        if (!$this->blk[$this->blklvl]['line_height']) {
                            $this->blk[$this->blklvl]['line_height'] = 'N';
                        } // mPDF 6
                        break;

                    // mPDF 6
                    case 'LINE-STACKING-STRATEGY':
                        $this->blk[$this->blklvl]['line_stacking_strategy'] = strtolower($v);
                        break;

                    case 'LINE-STACKING-SHIFT':
                        $this->blk[$this->blklvl]['line_stacking_shift'] = strtolower($v);
                        break;

                    case 'TEXT-ALIGN': // left right center justify
                        switch (strtoupper($v)) {
                            case 'LEFT':
                                $this->blk[$this->blklvl]['align'] = "L";
                                break;
                            case 'CENTER':
                                $this->blk[$this->blklvl]['align'] = "C";
                                break;
                            case 'RIGHT':
                                $this->blk[$this->blklvl]['align'] = "R";
                                break;
                            case 'JUSTIFY':
                                $this->blk[$this->blklvl]['align'] = "J";
                                break;
                        }
                        break;

                    /* -- BACKGROUNDS -- */
                    case 'BACKGROUND-GRADIENT':
                        if ($type == 'BLOCK') {
                            $this->blk[$this->blklvl]['gradient'] = $v;
                        }
                        break;
                    /* -- END BACKGROUNDS -- */

                    case 'DIRECTION':
                        if ($v) {
                            $this->blk[$this->blklvl]['direction'] = strtolower($v);
                        }
                        break;
                }
            }

            // FOR INLINE ONLY
            if ($type == 'INLINE') {
                switch ($k) {
                    case 'DISPLAY':
                        if (strtoupper($v) == 'NONE') {
                            $this->inlineDisplayOff = true;
                        }
                        break;
                    case 'DIRECTION':
                        break;
                }
            }
            // FOR INLINE ONLY
            if ($type == 'INLINE') {
                switch ($k) {
                    // BORDERS
                    case 'BORDER-TOP':
                        $this->spanborddet['T'] = $this->border_details($v);
                        $this->spanborder = true;
                        $spanbordset = true;
                        break;
                    case 'BORDER-BOTTOM':
                        $this->spanborddet['B'] = $this->border_details($v);
                        $this->spanborder = true;
                        $spanbordset = true;
                        break;
                    case 'BORDER-LEFT':
                        $this->spanborddet['L'] = $this->border_details($v);
                        $this->spanborder = true;
                        $spanbordset = true;
                        break;
                    case 'BORDER-RIGHT':
                        $this->spanborddet['R'] = $this->border_details($v);
                        $this->spanborder = true;
                        $spanbordset = true;
                        break;
                    case 'VISIBILITY': // block is set in OpenTag
                        $v = strtolower($v);
                        if ($v == 'visible' || $v == 'hidden' || $v == 'printonly' || $v == 'screenonly') {
                            $this->textparam['visibility'] = $v;
                        }
                        break;
                }//end of switch($k)
            }

            if ($type != 'TABLECELL') {
                // FOR INLINE and BLOCK
                switch ($k) {
                    case 'TEXT-ALIGN': // left right center justify
                        if (strtoupper($v) == 'NOJUSTIFY' && $this->blk[$this->blklvl]['align'] == "J") {
                            $this->blk[$this->blklvl]['align'] = "";
                        }
                        break;
                    // bgcolor only - to stay consistent with original html2fpdf
                    case 'BACKGROUND':
                    case 'BACKGROUND-COLOR':
                        $cor = $this->colorConverter->convert($v, $this->PDFAXwarnings);
                        if ($cor) {
                            if ($tag == 'BODY') {
                                $this->bodyBackgroundColor = $cor;
                            } elseif ($type == 'INLINE') {
                                $this->spanbgcolorarray = $cor;
                                $this->spanbgcolor = true;
                                $spanbgset = true;
                            } else {
                                $this->blk[$this->blklvl]['bgcolorarray'] = $cor;
                                $this->blk[$this->blklvl]['bgcolor'] = true;
                            }
                        } elseif ($type != 'INLINE') {
                            if ($this->ColActive) {
                                $this->blk[$this->blklvl]['bgcolorarray'] = $this->blk[$prevlevel]['bgcolorarray'];
                                $this->blk[$this->blklvl]['bgcolor'] = $this->blk[$prevlevel]['bgcolor'];
                            }
                        }
                        break;

                    case 'VERTICAL-ALIGN': // super and sub only dealt with here e.g. <SUB> and <SUP>
                        switch (strtoupper($v)) {
                            case 'SUPER':
                                $this->textvar = ($this->textvar | TextVars::FA_SUPERSCRIPT); // mPDF 5.7.1
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUBSCRIPT);
                                // mPDF 5.7.3  inline text-decoration parameters
                                if (isset($this->textparam['text-baseline'])) {
                                    $this->textparam['text-baseline'] += ($this->baselineSup) * $preceeding_fontsize;
                                } else {
                                    $this->textparam['text-baseline'] = ($this->baselineSup) * $preceeding_fontsize;
                                }
                                break;
                            case 'SUB':
                                $this->textvar = ($this->textvar | TextVars::FA_SUBSCRIPT);
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUPERSCRIPT);
                                // mPDF 5.7.3  inline text-decoration parameters
                                if (isset($this->textparam['text-baseline'])) {
                                    $this->textparam['text-baseline'] += ($this->baselineSub) * $preceeding_fontsize;
                                } else {
                                    $this->textparam['text-baseline'] = ($this->baselineSub) * $preceeding_fontsize;
                                }
                                break;
                            case 'BASELINE':
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUBSCRIPT);
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUPERSCRIPT);
                                // mPDF 5.7.3  inline text-decoration parameters
                                if (isset($this->textparam['text-baseline'])) {
                                    unset($this->textparam['text-baseline']);
                                }
                                break;
                            // mPDF 5.7.3  inline text-decoration parameters
                            default:
                                $lh = $this->_computeLineheight($this->blk[$this->blklvl]['line_height']);
                                $sz = $this->sizeConverter->convert($v, $lh, $this->FontSize, false);
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUBSCRIPT);
                                $this->textvar = ($this->textvar & ~TextVars::FA_SUPERSCRIPT);
                                if ($sz) {
                                    if ($sz > 0) {
                                        $this->textvar = ($this->textvar | TextVars::FA_SUPERSCRIPT);
                                    } else {
                                        $this->textvar = ($this->textvar | TextVars::FA_SUBSCRIPT);
                                    }
                                    if (isset($this->textparam['text-baseline'])) {
                                        $this->textparam['text-baseline'] += $sz;
                                    } else {
                                        $this->textparam['text-baseline'] = $sz;
                                    }
                                }
                        }
                        break;
                }//end of switch($k)
            }


            // FOR ALL
            switch ($k) {
                case 'LETTER-SPACING':
                    $this->lSpacingCSS = $v;
                    if (($this->lSpacingCSS || $this->lSpacingCSS === '0') && strtoupper($this->lSpacingCSS) != 'NORMAL') {
                        $this->fixedlSpacing = $this->sizeConverter->convert($this->lSpacingCSS, $this->FontSize);
                    }
                    break;

                case 'WORD-SPACING':
                    $this->wSpacingCSS = $v;
                    if ($this->wSpacingCSS && strtoupper($this->wSpacingCSS) != 'NORMAL') {
                        $this->minwSpacing = $this->sizeConverter->convert($this->wSpacingCSS, $this->FontSize);
                    }
                    break;

                case 'FONT-STYLE': // italic normal oblique
                    switch (strtoupper($v)) {
                        case 'ITALIC':
                        case 'OBLIQUE':
                            $this->SetStyle('I', true);
                            break;
                        case 'NORMAL':
                            $this->SetStyle('I', false);
                            break;
                    }
                    break;

                case 'FONT-WEIGHT': // normal bold // Does not support: bolder, lighter, 100..900(step value=100)
                    switch (strtoupper($v)) {
                        case 'BOLD':
                            $this->SetStyle('B', true);
                            break;
                        case 'NORMAL':
                            $this->SetStyle('B', false);
                            break;
                    }
                    break;

                case 'FONT-KERNING':
                    if (strtoupper($v) == 'NORMAL' || (strtoupper($v) == 'AUTO' && $this->useKerning)) {
                        /* -- OTL -- */
                        if ($this->CurrentFont['haskernGPOS']) {
                            if (isset($this->OTLtags['Plus'])) {
                                $this->OTLtags['Plus'] .= ' kern';
                            } else {
                                $this->OTLtags['Plus'] = ' kern';
                            }
                        } /* -- END OTL -- */ else {  // *OTL*
                            $this->textvar = ($this->textvar | TextVars::FC_KERNING);
                        } // *OTL*
                    } elseif (strtoupper($v) == 'NONE' || (strtoupper($v) == 'AUTO' && !$this->useKerning)) {
                        if (isset($this->OTLtags['Plus'])) {
                            $this->OTLtags['Plus'] = str_replace('kern', '', $this->OTLtags['Plus']); // *OTL*
                        }
                        if (isset($this->OTLtags['FFPlus'])) {
                            $this->OTLtags['FFPlus'] = preg_replace('/kern[\d]*/', '', $this->OTLtags['FFPlus']);
                        }
                        $this->textvar = ($this->textvar & ~TextVars::FC_KERNING);
                    }
                    break;

                /* -- OTL -- */
                case 'FONT-LANGUAGE-OVERRIDE':
                    $v = strtoupper($v);
                    if (strpos($v, 'NORMAL') !== false) {
                        $this->fontLanguageOverride = '';
                    } else {
                        $this->fontLanguageOverride = trim($v);
                    }
                    break;


                case 'FONT-VARIANT-POSITION':
                    if (isset($this->OTLtags['Plus'])) {
                        $this->OTLtags['Plus'] = str_replace(['sups', 'subs'], '', $this->OTLtags['Plus']);
                    }
                    switch (strtoupper($v)) {
                        case 'SUPER':
                            $this->OTLtags['Plus'] .= ' sups';
                            break;
                        case 'SUB':
                            $this->OTLtags['Plus'] .= ' subs';
                            break;
                        case 'NORMAL':
                            break;
                    }
                    break;

                case 'FONT-VARIANT-CAPS':
                    $v = strtoupper($v);
                    if (!isset($this->OTLtags['Plus'])) {
                        $this->OTLtags['Plus'] = '';
                    }
                    $this->OTLtags['Plus'] = str_replace(['c2sc', 'smcp', 'c2pc', 'pcap', 'unic', 'titl'], '', $this->OTLtags['Plus']);
                    $this->textvar = ($this->textvar & ~TextVars::FC_SMALLCAPS);   // ?????????????? <small-caps>
                    if (strpos($v, 'ALL-SMALL-CAPS') !== false) {
                        $this->OTLtags['Plus'] .= ' c2sc smcp';
                    } elseif (strpos($v, 'SMALL-CAPS') !== false) {
                        if (isset($this->CurrentFont['hassmallcapsGSUB']) && $this->CurrentFont['hassmallcapsGSUB']) {
                            $this->OTLtags['Plus'] .= ' smcp';
                        } else {
                            $this->textvar = ($this->textvar | TextVars::FC_SMALLCAPS);
                        }
                    } elseif (strpos($v, 'ALL-PETITE-CAPS') !== false) {
                        $this->OTLtags['Plus'] .= ' c2pc pcap';
                    } elseif (strpos($v, 'PETITE-CAPS') !== false) {
                        $this->OTLtags['Plus'] .= ' pcap';
                    } elseif (strpos($v, 'UNICASE') !== false) {
                        $this->OTLtags['Plus'] .= ' unic';
                    } elseif (strpos($v, 'TITLING-CAPS') !== false) {
                        $this->OTLtags['Plus'] .= ' titl';
                    }
                    break;

                case 'FONT-VARIANT-LIGATURES':
                    $v = strtoupper($v);
                    if (!isset($this->OTLtags['Plus'])) {
                        $this->OTLtags['Plus'] = '';
                    }
                    if (!isset($this->OTLtags['Minus'])) {
                        $this->OTLtags['Minus'] = '';
                    }
                    if (strpos($v, 'NORMAL') !== false) {
                        $this->OTLtags['Minus'] = str_replace(['liga', 'clig', 'calt'], '', $this->OTLtags['Minus']);
                        $this->OTLtags['Plus'] = str_replace(['dlig', 'hlig'], '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'NONE') !== false) {
                        $this->OTLtags['Minus'] .= ' liga clig calt';
                        $this->OTLtags['Plus'] = str_replace(['dlig', 'hlig'], '', $this->OTLtags['Plus']);
                    }
                    if (strpos($v, 'NO-COMMON-LIGATURES') !== false) {
                        $this->OTLtags['Minus'] .= ' liga clig';
                    } elseif (strpos($v, 'COMMON-LIGATURES') !== false) {
                        $this->OTLtags['Minus'] = str_replace(['liga', 'clig'], '', $this->OTLtags['Minus']);
                    }
                    if (strpos($v, 'NO-CONTEXTUAL') !== false) {
                        $this->OTLtags['Minus'] .= ' calt';
                    } elseif (strpos($v, 'CONTEXTUAL') !== false) {
                        $this->OTLtags['Minus'] = str_replace('calt', '', $this->OTLtags['Minus']);
                    }
                    if (strpos($v, 'NO-DISCRETIONARY-LIGATURES') !== false) {
                        $this->OTLtags['Plus'] = str_replace('dlig', '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'DISCRETIONARY-LIGATURES') !== false) {
                        $this->OTLtags['Plus'] .= ' dlig';
                    }
                    if (strpos($v, 'NO-HISTORICAL-LIGATURES') !== false) {
                        $this->OTLtags['Plus'] = str_replace('hlig', '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'HISTORICAL-LIGATURES') !== false) {
                        $this->OTLtags['Plus'] .= ' hlig';
                    }

                    break;

                case 'FONT-VARIANT-NUMERIC':
                    $v = strtoupper($v);
                    if (!isset($this->OTLtags['Plus'])) {
                        $this->OTLtags['Plus'] = '';
                    }
                    if (strpos($v, 'NORMAL') !== false) {
                        $this->OTLtags['Plus'] = str_replace(['ordn', 'zero', 'lnum', 'onum', 'pnum', 'tnum', 'frac', 'afrc'], '', $this->OTLtags['Plus']);
                    }
                    if (strpos($v, 'ORDINAL') !== false) {
                        $this->OTLtags['Plus'] .= ' ordn';
                    }
                    if (strpos($v, 'SLASHED-ZERO') !== false) {
                        $this->OTLtags['Plus'] .= ' zero';
                    }
                    if (strpos($v, 'LINING-NUMS') !== false) {
                        $this->OTLtags['Plus'] .= ' lnum';
                        $this->OTLtags['Plus'] = str_replace('onum', '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'OLDSTYLE-NUMS') !== false) {
                        $this->OTLtags['Plus'] .= ' onum';
                        $this->OTLtags['Plus'] = str_replace('lnum', '', $this->OTLtags['Plus']);
                    }
                    if (strpos($v, 'PROPORTIONAL-NUMS') !== false) {
                        $this->OTLtags['Plus'] .= ' pnum';
                        $this->OTLtags['Plus'] = str_replace('tnum', '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'TABULAR-NUMS') !== false) {
                        $this->OTLtags['Plus'] .= ' tnum';
                        $this->OTLtags['Plus'] = str_replace('pnum', '', $this->OTLtags['Plus']);
                    }
                    if (strpos($v, 'DIAGONAL-FRACTIONS') !== false) {
                        $this->OTLtags['Plus'] .= ' frac';
                        $this->OTLtags['Plus'] = str_replace('afrc', '', $this->OTLtags['Plus']);
                    } elseif (strpos($v, 'STACKED-FRACTIONS') !== false) {
                        $this->OTLtags['Plus'] .= ' afrc';
                        $this->OTLtags['Plus'] = str_replace('frac', '', $this->OTLtags['Plus']);
                    }
                    break;

                case 'FONT-VARIANT-ALTERNATES':  // Only supports historical-forms
                    $v = strtoupper($v);
                    if (!isset($this->OTLtags['Plus'])) {
                        $this->OTLtags['Plus'] = '';
                    }
                    if (strpos($v, 'NORMAL') !== false) {
                        $this->OTLtags['Plus'] = str_replace('hist', '', $this->OTLtags['Plus']);
                    }
                    if (strpos($v, 'HISTORICAL-FORMS') !== false) {
                        $this->OTLtags['Plus'] .= ' hist';
                    }
                    break;


                case 'FONT-FEATURE-SETTINGS':
                    $v = strtolower($v);
                    if (strpos($v, 'normal') !== false) {
                        $this->OTLtags['FFMinus'] = '';
                        $this->OTLtags['FFPlus'] = '';
                    } else {
                        if (!isset($this->OTLtags['FFPlus'])) {
                            $this->OTLtags['FFPlus'] = '';
                        }
                        if (!isset($this->OTLtags['FFMinus'])) {
                            $this->OTLtags['FFMinus'] = '';
                        }
                        $tags = preg_split('/[,]/', $v);
                        foreach ($tags as $t) {
                            if (preg_match('/[\"\']([a-zA-Z0-9]{4})[\"\']\s*(on|off|\d*){0,1}/', $t, $m)) {
                                if ($m[2] == 'off' || $m[2] === '0') {
                                    if (strpos($this->OTLtags['FFMinus'], $m[1]) === false) {
                                        $this->OTLtags['FFMinus'] .= ' ' . $m[1];
                                    }
                                    $this->OTLtags['FFPlus'] = preg_replace('/' . $m[1] . '[\d]*/', '', $this->OTLtags['FFPlus']);
                                } else {
                                    if ($m[2] == 'on') {
                                        $m[2] = '1';
                                    }
                                    if (strpos($this->OTLtags['FFPlus'], $m[1]) === false) {
                                        $this->OTLtags['FFPlus'] .= ' ' . $m[1] . $m[2];
                                    }
                                    $this->OTLtags['FFMinus'] = str_replace($m[1], '', $this->OTLtags['FFMinus']);
                                }
                            }
                        }
                    }
                    break;
                /* -- END OTL -- */


                case 'TEXT-TRANSFORM': // none uppercase lowercase // Does support: capitalize
                    switch (strtoupper($v)) { // Not working 100%
                        case 'CAPITALIZE':
                            $this->textvar = ($this->textvar | TextVars::FT_CAPITALIZE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_UPPERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_LOWERCASE); // mPDF 5.7.1
                            break;
                        case 'UPPERCASE':
                            $this->textvar = ($this->textvar | TextVars::FT_UPPERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_LOWERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_CAPITALIZE); // mPDF 5.7.1
                            break;
                        case 'LOWERCASE':
                            $this->textvar = ($this->textvar | TextVars::FT_LOWERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_UPPERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_CAPITALIZE); // mPDF 5.7.1
                            break;
                        case 'NONE':
                            break;
                            $this->textvar = ($this->textvar & ~TextVars::FT_UPPERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_LOWERCASE); // mPDF 5.7.1
                            $this->textvar = ($this->textvar & ~TextVars::FT_CAPITALIZE); // mPDF 5.7.1
                    }
                    break;

                case 'TEXT-SHADOW':
                    $ts = $this->cssManager->setCSStextshadow($v);
                    if ($ts) {
                        $this->textshadow = $ts;
                    }
                    break;

                case 'HYPHENS':
                    if (strtoupper($v) == 'NONE') {
                        $this->textparam['hyphens'] = 2;
                    } elseif (strtoupper($v) == 'AUTO') {
                        $this->textparam['hyphens'] = 1;
                    } elseif (strtoupper($v) == 'MANUAL') {
                        $this->textparam['hyphens'] = 0;
                    }
                    break;

                case 'TEXT-OUTLINE':
                    if (strtoupper($v) == 'NONE') {
                        $this->textparam['outline-s'] = false;
                    }
                    break;

                case 'TEXT-OUTLINE-WIDTH':
                case 'OUTLINE-WIDTH':
                    switch (strtoupper($v)) {
                        case 'THIN':
                            $v = '0.03em';
                            break;
                        case 'MEDIUM':
                            $v = '0.05em';
                            break;
                        case 'THICK':
                            $v = '0.07em';
                            break;
                    }
                    $w = $this->sizeConverter->convert($v, $this->FontSize, $this->FontSize);
                    if ($w) {
                        $this->textparam['outline-WIDTH'] = $w;
                        $this->textparam['outline-s'] = true;
                    } else {
                        $this->textparam['outline-s'] = false;
                    }
                    break;

                case 'TEXT-OUTLINE-COLOR':
                case 'OUTLINE-COLOR':
                    if (strtoupper($v) == 'INVERT') {
                        if ($this->colorarray) {
                            $cor = $this->colorarray;
                            $this->textparam['outline-COLOR'] = $this->colorConverter->invert($cor);
                        } else {
                            $this->textparam['outline-COLOR'] = $this->colorConverter->convert(255, $this->PDFAXwarnings);
                        }
                    } else {
                        $cor = $this->colorConverter->convert($v, $this->PDFAXwarnings);
                        if ($cor) {
                            $this->textparam['outline-COLOR'] = $cor;
                        }
                    }
                    break;

                case 'COLOR': // font color
                    $cor = $this->colorConverter->convert($v, $this->PDFAXwarnings);
                    if ($cor) {
                        $this->colorarray = $cor;
                        $this->SetTColor($cor);
                    }
                    break;
            }//end of switch($k)
        }//end of foreach
        // mPDF 5.7.3  inline text-decoration parameters
        // Needs to be set at the end - after vertical-align = super/sub, so that textparam['text-baseline'] is set
        if (isset($arrayaux['TEXT-DECORATION'])) {
            $v = $arrayaux['TEXT-DECORATION']; // none underline line-through (strikeout) // Does not support: blink
            if (stristr($v, 'LINE-THROUGH')) {
                $this->textvar = ($this->textvar | TextVars::FD_LINETHROUGH);
                // mPDF 5.7.3  inline text-decoration parameters
                if (isset($this->textparam['text-baseline'])) {
                    $this->textparam['s-decoration']['baseline'] = $this->textparam['text-baseline'];
                } else {
                    $this->textparam['s-decoration']['baseline'] = 0;
                }
                $this->textparam['s-decoration']['fontkey'] = $this->FontFamily . $this->FontStyle;
                $this->textparam['s-decoration']['fontsize'] = $this->FontSize;
                $this->textparam['s-decoration']['color'] = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            }
            if (stristr($v, 'UNDERLINE')) {
                $this->textvar = ($this->textvar | TextVars::FD_UNDERLINE);
                // mPDF 5.7.3  inline text-decoration parameters
                if (isset($this->textparam['text-baseline'])) {
                    $this->textparam['u-decoration']['baseline'] = $this->textparam['text-baseline'];
                } else {
                    $this->textparam['u-decoration']['baseline'] = 0;
                }
                $this->textparam['u-decoration']['fontkey'] = $this->FontFamily . $this->FontStyle;
                $this->textparam['u-decoration']['fontsize'] = $this->FontSize;
                $this->textparam['u-decoration']['color'] = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            }
            if (stristr($v, 'OVERLINE')) {
                $this->textvar = ($this->textvar | TextVars::FD_OVERLINE);
                // mPDF 5.7.3  inline text-decoration parameters
                if (isset($this->textparam['text-baseline'])) {
                    $this->textparam['o-decoration']['baseline'] = $this->textparam['text-baseline'];
                } else {
                    $this->textparam['o-decoration']['baseline'] = 0;
                }
                $this->textparam['o-decoration']['fontkey'] = $this->FontFamily . $this->FontStyle;
                $this->textparam['o-decoration']['fontsize'] = $this->FontSize;
                $this->textparam['o-decoration']['color'] = strtoupper($this->TextColor); // change 0 0 0 rg to 0 0 0 RG
            }
            if (stristr($v, 'NONE')) {
                $this->textvar = ($this->textvar & ~TextVars::FD_UNDERLINE);
                $this->textvar = ($this->textvar & ~TextVars::FD_LINETHROUGH);
                $this->textvar = ($this->textvar & ~TextVars::FD_OVERLINE);
                // mPDF 5.7.3  inline text-decoration parameters
                if (isset($this->textparam['u-decoration'])) {
                    unset($this->textparam['u-decoration']);
                }
                if (isset($this->textparam['s-decoration'])) {
                    unset($this->textparam['s-decoration']);
                }
                if (isset($this->textparam['o-decoration'])) {
                    unset($this->textparam['o-decoration']);
                }
            }
        }
        // mPDF 6
        if ($spanbordset) { // BORDER has been set on this INLINE element
            if (isset($this->textparam['text-baseline'])) {
                $this->textparam['bord-decoration']['baseline'] = $this->textparam['text-baseline'];
            } else {
                $this->textparam['bord-decoration']['baseline'] = 0;
            }
            $this->textparam['bord-decoration']['fontkey'] = $this->FontFamily . $this->FontStyle;
            $this->textparam['bord-decoration']['fontsize'] = $this->FontSize;
        }
        if ($spanbgset) { // BACKGROUND[-COLOR] has been set on this INLINE element
            if (isset($this->textparam['text-baseline'])) {
                $this->textparam['bg-decoration']['baseline'] = $this->textparam['text-baseline'];
            } else {
                $this->textparam['bg-decoration']['baseline'] = 0;
            }
            $this->textparam['bg-decoration']['fontkey'] = $this->FontFamily . $this->FontStyle;
            $this->textparam['bg-decoration']['fontsize'] = $this->FontSize;
        }
    }

    /* -- END HTML-CSS -- */

    public function SetStyle($tag, $enable)
    {
        $this->$tag = $enable;
        $style = '';
        foreach (['B', 'I'] as $s) {
            if ($this->$s) {
                $style.=$s;
            }
        }
        $this->currentfontstyle = $style;
        $this->SetFont('', $style, 0, false);
    }

    // Set multiple styles at one time
    public function SetStylesArray($arr)
    {
        $style = '';
        foreach (['B', 'I'] as $s) {
            if (isset($arr[$s])) {
                if ($arr[$s]) {
                    $this->$s = true;
                    $style.=$s;
                } else {
                    $this->$s = false;
                }
            } elseif ($this->$s) {
                $style.=$s;
            }
        }
        $this->currentfontstyle = $style;
        $this->SetFont('', $style, 0, false);
    }

    // Set multiple styles at one $str e.g. "BI"
    public function SetStyles($str)
    {
        $style = '';
        foreach (['B', 'I'] as $s) {
            if (strpos($str, $s) !== false) {
                $this->$s = true;
                $style.=$s;
            } else {
                $this->$s = false;
            }
        }
        $this->currentfontstyle = $style;
        $this->SetFont('', $style, 0, false);
    }

    public function ResetStyles()
    {
        foreach (['B', 'I'] as $s) {
            $this->$s = false;
        }
        $this->currentfontstyle = '';
        $this->SetFont('', '', 0, false);
    }

    public function DisableTags($str = '')
    {
        if ($str == '') { // enable all tags
            // Insert new supported tags in the long string below.
            $this->enabledtags = "<a><acronym><address><article><aside><b><bdi><bdo><big><blockquote><br><caption><center><cite><code><del><details><dd><div><dl><dt><em><fieldset><figcaption><figure><font><form><h1><h2><h3><h4><h5><h6><hgroup><hr><i><img><input><ins><kbd><legend><li><main><mark><meter><nav><ol><option><p><pre><progress><q><s><samp><section><select><small><span><strike><strong><sub><summary><sup><table><tbody><td><template><textarea><tfoot><th><thead><time><tr><tt><u><ul><var><footer><header><annotation><bookmark><textcircle><barcode><dottab><indexentry><indexinsert><watermarktext><watermarkimage><tts><ttz><tta><column_break><columnbreak><newcolumn><newpage><page_break><pagebreak><formfeed><columns><toc><tocentry><tocpagebreak><pageheader><pagefooter><setpageheader><setpagefooter><sethtmlpageheader><sethtmlpagefooter>";
        } else {
            $str = explode(",", $str);
            foreach ($str as $v) {
                $this->enabledtags = str_replace(trim($v), '', $this->enabledtags);
            }
        }
    }

    /* -- TABLES -- */

    public function TableCheckMinWidth($maxwidth, $forcewrap = 0, $textbuffer = [], $checkletter = false)
    {
    // mPDF 6
        $acclength = 0; // mPDF 6 (accumulated length across > 1 chunk)
        $acclongest = 0; // mPDF 6 (accumulated length max across > 1 chunk)
        $biggestword = 0;
        $toonarrow = false;
        if ((count($textbuffer) == 0) or ( (count($textbuffer) == 1) && ($textbuffer[0][0] == ''))) {
            return 0;
        }

        foreach ($textbuffer as $chunk) {
            $line = $chunk[0];
            $OTLdata = (isset($chunk[18]) ? $chunk[18] : null);

            // mPDF ITERATION
            if ($this->iterationCounter) {
                $line = preg_replace('/{iteration ([a-zA-Z0-9_]+)}/', '\\1', $line);
            }

            // IMAGES & FORM ELEMENTS
            if (substr($line, 0, 3) == "\xbb\xa4\xac") { // inline object - FORM element or IMAGE!
                $objattr = $this->_getObjAttr($line);
                if ($objattr['type'] != 'hr' && isset($objattr['width']) && ($objattr['width'] / $this->shrin_k) > ($maxwidth + 0.0001)) {
                    if (($objattr['width'] / $this->shrin_k) > $biggestword) {
                        $biggestword = ($objattr['width'] / $this->shrin_k);
                    }
                    $toonarrow = true;
                }
                continue;
            }

            if ($line == "\n") {
                $acclength = 0; // mPDF 6 (accumulated length across > 1 chunk)
                continue;
            }
            $line = trim($line);
            if (!empty($OTLdata)) {
                $this->otl->trimOTLdata($OTLdata, true, true);
            } // *OTL*
            // SET FONT SIZE/STYLE from $chunk[n]
            // FONTSIZE
            if (isset($chunk[11]) and $chunk[11] != '') {
                if ($this->shrin_k) {
                    $this->SetFontSize($chunk[11] / $this->shrin_k, false);
                } else {
                    $this->SetFontSize($chunk[11], false);
                }
            }
            // FONTFAMILY
            if (isset($chunk[4]) and $chunk[4] != '') {
                $font = $this->SetFont($chunk[4], $this->FontStyle, 0, false);
            }
            // B I
            if (isset($chunk[2]) and $chunk[2] != '') {
                $this->SetStyles($chunk[2]);
            }

            $lbw = $rbw = 0; // Border widths
            if (isset($chunk[16]) && !empty($chunk[16])) { // Border
                $this->spanborddet = $chunk[16];
                $lbw = (isset($this->spanborddet['L']['w']) ? $this->spanborddet['L']['w'] : 0);
                $rbw = (isset($this->spanborddet['R']['w']) ? $this->spanborddet['R']['w'] : 0);
            }
            if (isset($chunk[15])) {   // Word spacing
                $this->wSpacingCSS = $chunk[15];
                if ($this->wSpacingCSS && strtoupper($this->wSpacingCSS) != 'NORMAL') {
                    $this->minwSpacing = $this->sizeConverter->convert($this->wSpacingCSS, $this->FontSize) / $this->shrin_k; // mPDF 5.7.3
                }
            }
            if (isset($chunk[14])) {   // Letter spacing
                $this->lSpacingCSS = $chunk[14];
                if (($this->lSpacingCSS || $this->lSpacingCSS === '0') && strtoupper($this->lSpacingCSS) != 'NORMAL') {
                    $this->fixedlSpacing = $this->sizeConverter->convert($this->lSpacingCSS, $this->FontSize) / $this->shrin_k; // mPDF 5.7.3
                }
            }
            if (isset($chunk[8])) { // mPDF 5.7.1
                $this->textvar = $chunk[8];
            }

            // mPDF 6
            // If overflow==wrap ($checkletter) OR (No word breaks and contains CJK)
            if ($checkletter || (!preg_match('/(\xe2\x80\x8b| )/', trim($line)) && preg_match("/([" . $this->pregCJKchars . "])/u", $line) )) {
                if (preg_match("/([" . $this->pregCJKchars . "])/u", $line)) {
                    $checkCJK = true;
                } else {
                    $checkCJK = false;
                }

                $letters = preg_split('//u', $line);
                foreach ($letters as $k => $letter) {
                    // mPDF 6
                    if ($checkCJK) {
                        if (preg_match("/[" . $this->CJKleading . "]/u", $letter) && $k > 0) {
                            $letter = $letters[$k - 1] . $letter;
                        }
                        if (preg_match("/[" . $this->CJKfollowing . "]/u", $letter) && $k < (count($letters) - 1)) {
                            $letter = $letter . $letters[$k + 1];
                        }
                    }

                    $letterwidth = $this->GetStringWidth($letter, false, false, $chunk[8]); // Pass $textvar ($chunk[8]), but do OTLdata here
                    // so don't have to split OTLdata for each word
                    if ($k == 0) {
                        $letterwidth += $lbw;
                    }
                    if ($k == (count($letters) - 1)) {
                        $letterwidth += $rbw;
                    }

                    // Warn user that maxwidth is insufficient
                    if ($letterwidth > $maxwidth + 0.0001) {
                        if ($letterwidth > $biggestword) {
                            $biggestword = $letterwidth;
                        }
                        $toonarrow = true;
                    }
                }
            } else {
                // mPDF 6
                // Need to account for any XAdvance in GPOSinfo (OTLdata = $chunk[18])
                $wordXAdvance = [];
                if (isset($chunk[18]) && $chunk[18]) {
                    preg_match_all('/(\xe2\x80\x8b| )/', $line, $spaces, PREG_OFFSET_CAPTURE); // U+200B Zero Width word boundary, or space
                    $lastoffset = 0;
                    $k = -1; // Added so that if no spaces found, "last word" later is calculated for the one and only word
                    foreach ($spaces[0] as $k => $m) {
                        $offset = $m[1];
                        // ...TableCheckMinWidth...
                        // At this point, BIDI not applied, Writing direction is not set, and XAdvanceL balances XAdvanceR
                        for ($n = $lastoffset; $n < $offset; $n++) {
                            if (isset($chunk[18]['GPOSinfo'][$n]['XAdvanceL'])) {
                                if (isset($wordXAdvance[$k])) {
                                    $wordXAdvance[$k] += $chunk[18]['GPOSinfo'][$n]['XAdvanceL'];
                                } else {
                                    $wordXAdvance[$k] = $chunk[18]['GPOSinfo'][$n]['XAdvanceL'];
                                }
                            }
                        }
                        $lastoffset = $offset + 1;
                    }

                    $k++;  // last word
                    foreach ($chunk[18]['GPOSinfo'] as $n => $gpos) {
                        if ($n >= $lastoffset && isset($chunk[18]['GPOSinfo'][$n]['XAdvanceL'])) {
                            if (isset($wordXAdvance[$k])) {
                                $wordXAdvance[$k] += $chunk[18]['GPOSinfo'][$n]['XAdvanceL'];
                            } else {
                                $wordXAdvance[$k] = $chunk[18]['GPOSinfo'][$n]['XAdvanceL'];
                            }
                        }
                    }
                }

                $words = preg_split('/(\xe2\x80\x8b| )/', $line); // U+200B Zero Width word boundary, or space
                foreach ($words as $k => $word) {
                    $word = trim($word);
                    $wordwidth = $this->GetStringWidth($word, false, false, $chunk[8]); // Pass $textvar ($chunk[8]), but do OTLdata here
                    // so don't have to split OTLdata for each word
                    if (isset($wordXAdvance[$k])) {
                        $wordwidth += ($wordXAdvance[$k] * 1000 / $this->CurrentFont['unitsPerEm']) * ($this->FontSize / 1000);
                    }
                    if ($k == 0) {
                        $wordwidth += $lbw;
                    }
                    if ($k == (count($words) - 1)) {
                        $wordwidth += $rbw;
                    }

                    // mPDF 6
                    if (count($words) == 1 && substr($chunk[0], 0, 1) != ' ') {
                        $acclength += $wordwidth;
                    } elseif (count($words) > 1 && $k == 0 && substr($chunk[0], 0, 1) != ' ') {
                        $acclength += $wordwidth;
                    } else {
                        $acclength = $wordwidth;
                    }
                    $acclongest = max($acclongest, $acclength);
                    if (count($words) == 1 && substr($chunk[0], -1, 1) == ' ') {
                        $acclength = 0;
                    } elseif (count($words) > 1 && ($k != (count($words) - 1) || substr($chunk[0], -1, 1) == ' ')) {
                        $acclength = 0;
                    }

                    // Warn user that maxwidth is insufficient
                    if ($wordwidth > $maxwidth + 0.0001) {
                        if ($wordwidth > $biggestword) {
                            $biggestword = $wordwidth;
                        }
                        $toonarrow = true;
                    }
                }
            }

            // mPDF 6  Accumulated length of biggest word - across multiple chunks
            if ($acclongest > $maxwidth + 0.0001) {
                if ($acclongest > $biggestword) {
                    $biggestword = $acclongest;
                }
                $toonarrow = true;
            }

            // RESET FONT SIZE/STYLE
            // RESETTING VALUES
            // Now we must deactivate what we have used
            if (isset($chunk[2]) and $chunk[2] != '') {
                $this->ResetStyles();
            }
            if (isset($chunk[4]) and $chunk[4] != '') {
                $this->SetFont($this->default_font, $this->FontStyle, 0, false);
            }
            if (isset($chunk[11]) and $chunk[11] != '') {
                $this->SetFontSize($this->default_font_size, false);
            }
            $this->spanborddet = [];
            $this->textvar = 0x00; // mPDF 5.7.1
            $this->OTLtags = [];
            $this->lSpacingCSS = '';
            $this->wSpacingCSS = '';
            $this->fixedlSpacing = false;
            $this->minwSpacing = 0;
        }

        // Return -(wordsize) if word is bigger than maxwidth
        // ADDED
        if (($toonarrow) && ($this->table_error_report)) {
            throw new \Mpdf\MpdfException("Word is too long to fit in table - " . $this->table_error_report_param);
        }
        if ($toonarrow) {
            return -$biggestword;
        } else {
            return 1;
        }
    }

    public function shrinkTable(&$table, $k)
    {
        $table['border_spacing_H'] /= $k;
        $table['border_spacing_V'] /= $k;

        $table['padding']['T'] /= $k;
        $table['padding']['R'] /= $k;
        $table['padding']['B'] /= $k;
        $table['padding']['L'] /= $k;

        $table['margin']['T'] /= $k;
        $table['margin']['R'] /= $k;
        $table['margin']['B'] /= $k;
        $table['margin']['L'] /= $k;

        $table['border_details']['T']['w'] /= $k;
        $table['border_details']['R']['w'] /= $k;
        $table['border_details']['B']['w'] /= $k;
        $table['border_details']['L']['w'] /= $k;

        if (isset($table['max_cell_border_width']['T'])) {
            $table['max_cell_border_width']['T'] /= $k;
        }
        if (isset($table['max_cell_border_width']['R'])) {
            $table['max_cell_border_width']['R'] /= $k;
        }
        if (isset($table['max_cell_border_width']['B'])) {
            $table['max_cell_border_width']['B'] /= $k;
        }
        if (isset($table['max_cell_border_width']['L'])) {
            $table['max_cell_border_width']['L'] /= $k;
        }

        if ($this->simpleTables) {
            $table['simple']['border_details']['T']['w'] /= $k;
            $table['simple']['border_details']['R']['w'] /= $k;
            $table['simple']['border_details']['B']['w'] /= $k;
            $table['simple']['border_details']['L']['w'] /= $k;
        }

        $table['miw'] /= $k;
        $table['maw'] /= $k;

        for ($j = 0; $j < $table['nc']; $j++) { // columns

            $table['wc'][$j]['miw'] = isset($table['wc'][$j]['miw']) ? $table['wc'][$j]['miw'] : 0;
            $table['wc'][$j]['maw'] = isset($table['wc'][$j]['maw']) ? $table['wc'][$j]['maw'] : 0;

            $table['wc'][$j]['miw'] /= $k;
            $table['wc'][$j]['maw'] /= $k;

            if (isset($table['decimal_align'][$j]['maxs0']) && $table['decimal_align'][$j]['maxs0']) {
                $table['decimal_align'][$j]['maxs0'] /= $k;
            }

            if (isset($table['decimal_align'][$j]['maxs1']) && $table['decimal_align'][$j]['maxs1']) {
                $table['decimal_align'][$j]['maxs1'] /= $k;
            }

            if (isset($table['wc'][$j]['absmiw']) && $table['wc'][$j]['absmiw']) {
                $table['wc'][$j]['absmiw'] /= $k;
            }

            for ($i = 0; $i < $table['nr']; $i++) { // rows

                $c = &$table['cells'][$i][$j];

                if (isset($c) && $c) {

                    if (!$this->simpleTables) {

                        if ($this->packTableData) {

                            $cell = $this->_unpackCellBorder($c['borderbin']);

                            $cell['border_details']['T']['w'] /= $k;
                            $cell['border_details']['R']['w'] /= $k;
                            $cell['border_details']['B']['w'] /= $k;
                            $cell['border_details']['L']['w'] /= $k;
                            $cell['border_details']['mbw']['TL'] /= $k;
                            $cell['border_details']['mbw']['TR'] /= $k;
                            $cell['border_details']['mbw']['BL'] /= $k;
                            $cell['border_details']['mbw']['BR'] /= $k;
                            $cell['border_details']['mbw']['LT'] /= $k;
                            $cell['border_details']['mbw']['LB'] /= $k;
                            $cell['border_details']['mbw']['RT'] /= $k;
                            $cell['border_details']['mbw']['RB'] /= $k;

                            $c['borderbin'] = $this->_packCellBorder($cell);

                        } else {

                            $c['border_details']['T']['w'] /= $k;
                            $c['border_details']['R']['w'] /= $k;
                            $c['border_details']['B']['w'] /= $k;
                            $c['border_details']['L']['w'] /= $k;
                            $c['border_details']['mbw']['TL'] /= $k;
                            $c['border_details']['mbw']['TR'] /= $k;
                            $c['border_details']['mbw']['BL'] /= $k;
                            $c['border_details']['mbw']['BR'] /= $k;
                            $c['border_details']['mbw']['LT'] /= $k;
                            $c['border_details']['mbw']['LB'] /= $k;
                            $c['border_details']['mbw']['RT'] /= $k;
                            $c['border_details']['mbw']['RB'] /= $k;
                        }
                    }

                    $c['padding']['T'] /= $k;
                    $c['padding']['R'] /= $k;
                    $c['padding']['B'] /= $k;
                    $c['padding']['L'] /= $k;

                    $c['maxs'] = isset($c['maxs']) ? $c['maxs'] /= $k : 0;
                    $c['w'] = isset($c['w']) ? $c['w'] /= $k : 0;

                    $c['s'] = isset($c['s']) ? $c['s'] /= $k : 0;
                    $c['h'] = isset($c['h']) ? $c['h'] /= $k : 0;

                    $c['miw'] = isset($c['miw']) ? $c['miw'] /= $k : 0;
                    $c['maw'] = isset($c['maw']) ? $c['maw'] /= $k : 0;

                    $c['absmiw'] = isset($c['absmiw']) ? $c['absmiw'] /= $k : 0;

                    $c['nestedmaw'] = isset($c['nestedmaw']) ? $c['nestedmaw'] /= $k : 0;
                    $c['nestedmiw'] = isset($c['nestedmiw']) ? $c['nestedmiw'] /= $k : 0;

                    if (isset($c['textbuffer'])) {
                        foreach ($c['textbuffer'] as $n => $tb) {
                            if (!empty($tb[16])) {
                                !isset($c['textbuffer'][$n][16]['T']) || $c['textbuffer'][$n][16]['T']['w'] /= $k;
                                !isset($c['textbuffer'][$n][16]['B']) || $c['textbuffer'][$n][16]['B']['w'] /= $k;
                                !isset($c['textbuffer'][$n][16]['L']) || $c['textbuffer'][$n][16]['L']['w'] /= $k;
                                !isset($c['textbuffer'][$n][16]['R']) || $c['textbuffer'][$n][16]['R']['w'] /= $k;
                            }
                        }
                    }

                    unset($c);
                }

            } // rows
        } // columns
    }

    public function read_short(&$fh)
    {
        $s = fread($fh, 2);
        $a = (ord($s[0]) << 8) + ord($s[1]);
        if ($a & (1 << 15)) {
            $a = ($a - (1 << 16));
        }
        return $a;
    }

    /* -- TABLES -- */
    /* -- TABLES-ADVANCED-BORDERS -- */

    /* -- END TABLES-ADVANCED-BORDERS -- */

    public function setBorder(&$var, $flag, $set = true)
    {
        $flag = intval($flag);
        if ($set) {
            $set = true;
        }
        $var = intval($var);
        $var = $set ? ($var | $flag) : ($var & ~$flag);
    }

    public function issetBorder($var, $flag)
    {
        $flag = intval($flag);
        $var = intval($var);
        return (($var & $flag) == $flag);
    }

    /////////////////////////END OF TABLE CODE//////////////////////////////////
    /* -- END TABLES -- */

    /* -- IMPORTS -- */

    public function SetProtection($permissions = [], $user_pass = '', $owner_pass = null, $length = 40)
    {
        if (!$this->protection) {
            $this->protection = new Protection(new UniqidGenerator());
        }

        $this->encrypted = $this->protection->setProtection($permissions, $user_pass, $owner_pass, $length);
    }

    // =========================================
    /* -- BOOKMARKS -- */
    // FROM class PDF_Bookmark
    public function Bookmark($txt, $level = 0, $y = 0)
    {
        $txt = $this->purify_utf8_text($txt);
        if ($this->text_input_as_HTML) {
            $txt = $this->all_entities_to_utf8($txt);
        }
        if ($y == -1) {
            if (!$this->ColActive) {
                $y = $this->y;
            } else {
                $y = $this->y0;
            } // If columns are on - mark top of columns
        }
        // else y is used as set, or =0 i.e. top of page
        // DIRECTIONALITY RTL
        $bmo = ['t' => $txt, 'l' => $level, 'y' => $y, 'p' => $this->page];
        if ($this->keep_block_together) {
            // do nothing
        } /* -- TABLES -- */ elseif ($this->table_rotate) {
            $this->tbrot_BMoutlines[] = $bmo;
        } elseif ($this->kwt) {
            $this->kwt_BMoutlines[] = $bmo;
        } /* -- END TABLES -- */ elseif ($this->ColActive) { // *COLUMNS*
            $this->col_BMoutlines[] = $bmo; // *COLUMNS*
        } // *COLUMNS*
        else {
            $this->BMoutlines[] = $bmo;
        }
    }

    /* -- END BOOKMARKS -- */

    /**
     * Initiate, and Mark a place for the Table of Contents to be inserted
     */
    public function TOC(
        $tocfont = '',
        $tocfontsize = 0,
        $tocindent = 0,
        $resetpagenum = '',
        $pagenumstyle = '',
        $suppress = '',
        $toc_orientation = '',
        $TOCusePaging = true,
        $TOCuseLinking = false,
        $toc_id = 0,
        $tocoutdent = ''
    ) {

        $this->tableOfContents->TOC(
            $tocfont,
            $tocfontsize,
            $tocindent,
            $resetpagenum,
            $pagenumstyle,
            $suppress,
            $toc_orientation,
            $TOCusePaging,
            $TOCuseLinking,
            $toc_id,
            $tocoutdent
        );
    }

    public function TOCpagebreakByArray($a)
    {
        if (!is_array($a)) {
            $a = [];
        }
        $tocoutdent = (isset($a['tocoutdent']) ? $a['tocoutdent'] : (isset($a['outdent']) ? $a['outdent'] : ''));
        $TOCusePaging = (isset($a['TOCusePaging']) ? $a['TOCusePaging'] : (isset($a['paging']) ? $a['paging'] : true));
        $TOCuseLinking = (isset($a['TOCuseLinking']) ? $a['TOCuseLinking'] : (isset($a['links']) ? $a['links'] : ''));
        $toc_orientation = (isset($a['toc_orientation']) ? $a['toc_orientation'] : (isset($a['toc-orientation']) ? $a['toc-orientation'] : ''));
        $toc_mgl = (isset($a['toc_mgl']) ? $a['toc_mgl'] : (isset($a['toc-margin-left']) ? $a['toc-margin-left'] : ''));
        $toc_mgr = (isset($a['toc_mgr']) ? $a['toc_mgr'] : (isset($a['toc-margin-right']) ? $a['toc-margin-right'] : ''));
        $toc_mgt = (isset($a['toc_mgt']) ? $a['toc_mgt'] : (isset($a['toc-margin-top']) ? $a['toc-margin-top'] : ''));
        $toc_mgb = (isset($a['toc_mgb']) ? $a['toc_mgb'] : (isset($a['toc-margin-bottom']) ? $a['toc-margin-bottom'] : ''));
        $toc_mgh = (isset($a['toc_mgh']) ? $a['toc_mgh'] : (isset($a['toc-margin-header']) ? $a['toc-margin-header'] : ''));
        $toc_mgf = (isset($a['toc_mgf']) ? $a['toc_mgf'] : (isset($a['toc-margin-footer']) ? $a['toc-margin-footer'] : ''));
        $toc_ohname = (isset($a['toc_ohname']) ? $a['toc_ohname'] : (isset($a['toc-odd-header-name']) ? $a['toc-odd-header-name'] : ''));
        $toc_ehname = (isset($a['toc_ehname']) ? $a['toc_ehname'] : (isset($a['toc-even-header-name']) ? $a['toc-even-header-name'] : ''));
        $toc_ofname = (isset($a['toc_ofname']) ? $a['toc_ofname'] : (isset($a['toc-odd-footer-name']) ? $a['toc-odd-footer-name'] : ''));
        $toc_efname = (isset($a['toc_efname']) ? $a['toc_efname'] : (isset($a['toc-even-footer-name']) ? $a['toc-even-footer-name'] : ''));
        $toc_ohvalue = (isset($a['toc_ohvalue']) ? $a['toc_ohvalue'] : (isset($a['toc-odd-header-value']) ? $a['toc-odd-header-value'] : 0));
        $toc_ehvalue = (isset($a['toc_ehvalue']) ? $a['toc_ehvalue'] : (isset($a['toc-even-header-value']) ? $a['toc-even-header-value'] : 0));
        $toc_ofvalue = (isset($a['toc_ofvalue']) ? $a['toc_ofvalue'] : (isset($a['toc-odd-footer-value']) ? $a['toc-odd-footer-value'] : 0));
        $toc_efvalue = (isset($a['toc_efvalue']) ? $a['toc_efvalue'] : (isset($a['toc-even-footer-value']) ? $a['toc-even-footer-value'] : 0));
        $toc_preHTML = (isset($a['toc_preHTML']) ? $a['toc_preHTML'] : (isset($a['toc-preHTML']) ? $a['toc-preHTML'] : ''));
        $toc_postHTML = (isset($a['toc_postHTML']) ? $a['toc_postHTML'] : (isset($a['toc-postHTML']) ? $a['toc-postHTML'] : ''));
        $toc_bookmarkText = (isset($a['toc_bookmarkText']) ? $a['toc_bookmarkText'] : (isset($a['toc-bookmarkText']) ? $a['toc-bookmarkText'] : ''));
        $resetpagenum = (isset($a['resetpagenum']) ? $a['resetpagenum'] : '');
        $pagenumstyle = (isset($a['pagenumstyle']) ? $a['pagenumstyle'] : '');
        $suppress = (isset($a['suppress']) ? $a['suppress'] : '');
        $orientation = (isset($a['orientation']) ? $a['orientation'] : '');
        $mgl = (isset($a['mgl']) ? $a['mgl'] : (isset($a['margin-left']) ? $a['margin-left'] : ''));
        $mgr = (isset($a['mgr']) ? $a['mgr'] : (isset($a['margin-right']) ? $a['margin-right'] : ''));
        $mgt = (isset($a['mgt']) ? $a['mgt'] : (isset($a['margin-top']) ? $a['margin-top'] : ''));
        $mgb = (isset($a['mgb']) ? $a['mgb'] : (isset($a['margin-bottom']) ? $a['margin-bottom'] : ''));
        $mgh = (isset($a['mgh']) ? $a['mgh'] : (isset($a['margin-header']) ? $a['margin-header'] : ''));
        $mgf = (isset($a['mgf']) ? $a['mgf'] : (isset($a['margin-footer']) ? $a['margin-footer'] : ''));
        $ohname = (isset($a['ohname']) ? $a['ohname'] : (isset($a['odd-header-name']) ? $a['odd-header-name'] : ''));
        $ehname = (isset($a['ehname']) ? $a['ehname'] : (isset($a['even-header-name']) ? $a['even-header-name'] : ''));
        $ofname = (isset($a['ofname']) ? $a['ofname'] : (isset($a['odd-footer-name']) ? $a['odd-footer-name'] : ''));
        $efname = (isset($a['efname']) ? $a['efname'] : (isset($a['even-footer-name']) ? $a['even-footer-name'] : ''));
        $ohvalue = (isset($a['ohvalue']) ? $a['ohvalue'] : (isset($a['odd-header-value']) ? $a['odd-header-value'] : 0));
        $ehvalue = (isset($a['ehvalue']) ? $a['ehvalue'] : (isset($a['even-header-value']) ? $a['even-header-value'] : 0));
        $ofvalue = (isset($a['ofvalue']) ? $a['ofvalue'] : (isset($a['odd-footer-value']) ? $a['odd-footer-value'] : 0));
        $efvalue = (isset($a['efvalue']) ? $a['efvalue'] : (isset($a['even-footer-value']) ? $a['even-footer-value'] : 0));
        $toc_id = (isset($a['toc_id']) ? $a['toc_id'] : (isset($a['name']) ? $a['name'] : 0));
        $pagesel = (isset($a['pagesel']) ? $a['pagesel'] : (isset($a['pageselector']) ? $a['pageselector'] : ''));
        $toc_pagesel = (isset($a['toc_pagesel']) ? $a['toc_pagesel'] : (isset($a['toc-pageselector']) ? $a['toc-pageselector'] : ''));
        $sheetsize = (isset($a['sheetsize']) ? $a['sheetsize'] : (isset($a['sheet-size']) ? $a['sheet-size'] : ''));
        $toc_sheetsize = (isset($a['toc_sheetsize']) ? $a['toc_sheetsize'] : (isset($a['toc-sheet-size']) ? $a['toc-sheet-size'] : ''));

        $this->TOCpagebreak('', '', '', $TOCusePaging, $TOCuseLinking, $toc_orientation, $toc_mgl, $toc_mgr, $toc_mgt, $toc_mgb, $toc_mgh, $toc_mgf, $toc_ohname, $toc_ehname, $toc_ofname, $toc_efname, $toc_ohvalue, $toc_ehvalue, $toc_ofvalue, $toc_efvalue, $toc_preHTML, $toc_postHTML, $toc_bookmarkText, $resetpagenum, $pagenumstyle, $suppress, $orientation, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $toc_id, $pagesel, $toc_pagesel, $sheetsize, $toc_sheetsize, $tocoutdent);
    }

    public function TOCpagebreak($tocfont = '', $tocfontsize = '', $tocindent = '', $TOCusePaging = true, $TOCuseLinking = '', $toc_orientation = '', $toc_mgl = '', $toc_mgr = '', $toc_mgt = '', $toc_mgb = '', $toc_mgh = '', $toc_mgf = '', $toc_ohname = '', $toc_ehname = '', $toc_ofname = '', $toc_efname = '', $toc_ohvalue = 0, $toc_ehvalue = 0, $toc_ofvalue = 0, $toc_efvalue = 0, $toc_preHTML = '', $toc_postHTML = '', $toc_bookmarkText = '', $resetpagenum = '', $pagenumstyle = '', $suppress = '', $orientation = '', $mgl = '', $mgr = '', $mgt = '', $mgb = '', $mgh = '', $mgf = '', $ohname = '', $ehname = '', $ofname = '', $efname = '', $ohvalue = 0, $ehvalue = 0, $ofvalue = 0, $efvalue = 0, $toc_id = 0, $pagesel = '', $toc_pagesel = '', $sheetsize = '', $toc_sheetsize = '', $tocoutdent = '')
    {
        // Start a new page
        if ($this->state == 0) {
            $this->AddPage();
        }
        if ($this->y == $this->tMargin && (!$this->mirrorMargins || ($this->mirrorMargins && $this->page % 2 == 1))) {
            // Don't add a page
            if ($this->page == 1 && count($this->PageNumSubstitutions) == 0) {
                if (!$suppress) {
                    $suppress = 'off';
                }
                // $this->PageNumSubstitutions[] = array('from'=>1, 'reset'=> $resetpagenum, 'type'=>$pagenumstyle, 'suppress'=> $suppress);
            }
            $this->PageNumSubstitutions[] = ['from' => $this->page, 'reset' => $resetpagenum, 'type' => $pagenumstyle, 'suppress' => $suppress];
        } else {
            $this->AddPage($orientation, 'NEXT-ODD', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $sheetsize);
        }
        $this->tableOfContents->TOCpagebreak($tocfont, $tocfontsize, $tocindent, $TOCusePaging, $TOCuseLinking, $toc_orientation, $toc_mgl, $toc_mgr, $toc_mgt, $toc_mgb, $toc_mgh, $toc_mgf, $toc_ohname, $toc_ehname, $toc_ofname, $toc_efname, $toc_ohvalue, $toc_ehvalue, $toc_ofvalue, $toc_efvalue, $toc_preHTML, $toc_postHTML, $toc_bookmarkText, $resetpagenum, $pagenumstyle, $suppress, $orientation, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $toc_id, $pagesel, $toc_pagesel, $sheetsize, $toc_sheetsize, $tocoutdent);
    }

    public function TOC_Entry($txt, $level = 0, $toc_id = 0)
    {
        if ($this->ColActive) {
            $ily = $this->y0;
        } else {
            $ily = $this->y;
        } // use top of columns

        $linkn = $this->AddLink();
        $uid = '__mpdfinternallink_' . $linkn;
        if ($this->table_rotate) {
            $this->internallink[$uid] = ["Y" => $ily, "PAGE" => $this->page, "tbrot" => true];
        } elseif ($this->kwt) {
            $this->internallink[$uid] = ["Y" => $ily, "PAGE" => $this->page, "kwt" => true];
        } elseif ($this->ColActive) {
            $this->internallink[$uid] = ["Y" => $ily, "PAGE" => $this->page, "col" => $this->CurrCol];
        } elseif (!$this->keep_block_together) {
            $this->internallink[$uid] = ["Y" => $ily, "PAGE" => $this->page];
        }
        $this->internallink['#' . $uid] = $linkn;
        $this->SetLink($linkn, $ily, $this->page);

        if (strtoupper($toc_id) == 'ALL') {
            $toc_id = '_mpdf_all';
        } elseif (!$toc_id) {
            $toc_id = 0;
        } else {
            $toc_id = strtolower($toc_id);
        }
        $btoc = ['t' => $txt, 'l' => $level, 'p' => $this->page, 'link' => $linkn, 'toc_id' => $toc_id];
        if ($this->keep_block_together) {
            // do nothing
        } /* -- TABLES -- */ elseif ($this->table_rotate) {
            $this->tbrot_toc[] = $btoc;
        } elseif ($this->kwt) {
            $this->kwt_toc[] = $btoc;
        } /* -- END TABLES -- */ elseif ($this->ColActive) {  // *COLUMNS*
            $this->col_toc[] = $btoc; // *COLUMNS*
        } // *COLUMNS*
        else {
            $this->tableOfContents->_toc[] = $btoc;
        }
    }

    /* -- END TOC -- */

    // ======================================================
    public function MovePages($target_page, $start_page, $end_page = -1)
    {
        // move a page/pages EARLIER in the document
        if ($end_page < 1) {
            $end_page = $start_page;
        }
        $n_toc = $end_page - $start_page + 1;

        // Set/Update PageNumSubstitutions changes before moving anything
        if (count($this->PageNumSubstitutions)) {
            $tp_present = false;
            $sp_present = false;
            $ep_present = false;
            foreach ($this->PageNumSubstitutions as $k => $v) {
                if ($this->PageNumSubstitutions[$k]['from'] == $target_page) {
                    $tp_present = true;
                    if ($this->PageNumSubstitutions[$k]['suppress'] != 'on' && $this->PageNumSubstitutions[$k]['suppress'] != 1) {
                        $this->PageNumSubstitutions[$k]['suppress'] = 'off';
                    }
                }
                if ($this->PageNumSubstitutions[$k]['from'] == $start_page) {
                    $sp_present = true;
                    if ($this->PageNumSubstitutions[$k]['suppress'] != 'on' && $this->PageNumSubstitutions[$k]['suppress'] != 1) {
                        $this->PageNumSubstitutions[$k]['suppress'] = 'off';
                    }
                }
                if ($this->PageNumSubstitutions[$k]['from'] == ($end_page + 1)) {
                    $ep_present = true;
                    if ($this->PageNumSubstitutions[$k]['suppress'] != 'on' && $this->PageNumSubstitutions[$k]['suppress'] != 1) {
                        $this->PageNumSubstitutions[$k]['suppress'] = 'off';
                    }
                }
            }

            if (!$tp_present) {
                list($tp_type, $tp_suppress, $tp_reset) = $this->docPageSettings($target_page);
            }
            if (!$sp_present) {
                list($sp_type, $sp_suppress, $sp_reset) = $this->docPageSettings($start_page);
            }
            if (!$ep_present) {
                list($ep_type, $ep_suppress, $ep_reset) = $this->docPageSettings($start_page - 1);
            }
        }

        $last = [];
        // store pages
        for ($i = $start_page; $i <= $end_page; $i++) {
            $last[] = $this->pages[$i];
        }
        // move pages
        for ($i = $start_page - 1; $i >= ($target_page); $i--) {
            $this->pages[$i + $n_toc] = $this->pages[$i];
        }
        // Put toc pages at insert point
        for ($i = 0; $i < $n_toc; $i++) {
            $this->pages[$target_page + $i] = $last[$i];
        }

        /* -- BOOKMARKS -- */
        // Update Bookmarks
        foreach ($this->BMoutlines as $i => $o) {
            if ($o['p'] >= $target_page) {
                $this->BMoutlines[$i]['p'] += $n_toc;
            }
        }
        /* -- END BOOKMARKS -- */

        // Update Page Links
        if (count($this->PageLinks)) {
            $newarr = [];
            foreach ($this->PageLinks as $i => $o) {
                foreach ($this->PageLinks[$i] as $key => $pl) {
                    if (strpos($pl[4], '@') === 0) {
                        $p = substr($pl[4], 1);
                        if ($p >= $start_page && $p <= $end_page) {
                            $this->PageLinks[$i][$key][4] = '@' . ($p + ($target_page - $start_page));
                        } elseif ($p >= $target_page && $p < $start_page) {
                            $this->PageLinks[$i][$key][4] = '@' . ($p + $n_toc);
                        }
                    }
                }
                if ($i >= $start_page && $i <= $end_page) {
                    $newarr[($i + ($target_page - $start_page))] = $this->PageLinks[$i];
                } elseif ($i >= $target_page && $i < $start_page) {
                    $newarr[($i + $n_toc)] = $this->PageLinks[$i];
                } else {
                    $newarr[$i] = $this->PageLinks[$i];
                }
            }
            $this->PageLinks = $newarr;
        }

        // OrientationChanges
        if (count($this->OrientationChanges)) {
            $newarr = [];
            foreach ($this->OrientationChanges as $p => $v) {
                if ($p >= $start_page && $p <= $end_page) {
                    $newarr[($p + ($target_page - $start_page))] = $this->OrientationChanges[$p];
                } elseif ($p >= $target_page && $p < $start_page) {
                    $newarr[$p + $n_toc] = $this->OrientationChanges[$p];
                } else {
                    $newarr[$p] = $this->OrientationChanges[$p];
                }
            }
            ksort($newarr);
            $this->OrientationChanges = $newarr;
        }

        // Page Dimensions
        if (count($this->pageDim)) {
            $newarr = [];
            foreach ($this->pageDim as $p => $v) {
                if ($p >= $start_page && $p <= $end_page) {
                    $newarr[($p + ($target_page - $start_page))] = $this->pageDim[$p];
                } elseif ($p >= $target_page && $p < $start_page) {
                    $newarr[$p + $n_toc] = $this->pageDim[$p];
                } else {
                    $newarr[$p] = $this->pageDim[$p];
                }
            }
            ksort($newarr);
            $this->pageDim = $newarr;
        }

        // HTML Headers & Footers
        if (count($this->saveHTMLHeader)) {
            $newarr = [];
            foreach ($this->saveHTMLHeader as $p => $v) {
                if ($p >= $start_page && $p <= $end_page) {
                    $newarr[($p + ($target_page - $start_page))] = $this->saveHTMLHeader[$p];
                } elseif ($p >= $target_page && $p < $start_page) {
                    $newarr[$p + $n_toc] = $this->saveHTMLHeader[$p];
                } else {
                    $newarr[$p] = $this->saveHTMLHeader[$p];
                }
            }
            ksort($newarr);
            $this->saveHTMLHeader = $newarr;
        }
        if (count($this->saveHTMLFooter)) {
            $newarr = [];
            foreach ($this->saveHTMLFooter as $p => $v) {
                if ($p >= $start_page && $p <= $end_page) {
                    $newarr[($p + ($target_page - $start_page))] = $this->saveHTMLFooter[$p];
                } elseif ($p >= $target_page && $p < $start_page) {
                    $newarr[$p + $n_toc] = $this->saveHTMLFooter[$p];
                } else {
                    $newarr[$p] = $this->saveHTMLFooter[$p];
                }
            }
            ksort($newarr);
            $this->saveHTMLFooter = $newarr;
        }

        // Update Internal Links
        if (count($this->internallink)) {
            foreach ($this->internallink as $key => $o) {
                if ($o['PAGE'] >= $start_page && $o['PAGE'] <= $end_page) {
                    $this->internallink[$key]['PAGE'] += ($target_page - $start_page);
                } elseif ($o['PAGE'] >= $target_page && $o['PAGE'] < $start_page) {
                    $this->internallink[$key]['PAGE'] += $n_toc;
                }
            }
        }

        // Update Links
        if (count($this->links)) {
            foreach ($this->links as $key => $o) {
                if ($o[0] >= $start_page && $o[0] <= $end_page) {
                    $this->links[$key][0] += ($target_page - $start_page);
                }
                if ($o[0] >= $target_page && $o[0] < $start_page) {
                    $this->links[$key][0] += $n_toc;
                }
            }
        }

        // Update Form fields
        if (count($this->form->forms)) {
            foreach ($this->form->forms as $key => $f) {
                if ($f['page'] >= $start_page && $f['page'] <= $end_page) {
                    $this->form->forms[$key]['page'] += ($target_page - $start_page);
                }
                if ($f['page'] >= $target_page && $f['page'] < $start_page) {
                    $this->form->forms[$key]['page'] += $n_toc;
                }
            }
        }

        /* -- ANNOTATIONS -- */
        // Update Annotations
        if (count($this->PageAnnots)) {
            $newarr = [];
            foreach ($this->PageAnnots as $p => $anno) {
                if ($p >= $start_page && $p <= $end_page) {
                    $np = $p + ($target_page - $start_page);
                    foreach ($anno as $o) {
                        $newarr[$np][] = $o;
                    }
                } elseif ($p >= $target_page && $p < $start_page) {
                    $np = $p + $n_toc;
                    foreach ($anno as $o) {
                        $newarr[$np][] = $o;
                    }
                } else {
                    $newarr[$p] = $this->PageAnnots[$p];
                }
            }
            $this->PageAnnots = $newarr;
            unset($newarr);
        }
        /* -- END ANNOTATIONS -- */

        // Update TOC pages
        if (count($this->tableOfContents->_toc)) {
            foreach ($this->tableOfContents->_toc as $key => $t) {
                if ($t['p'] >= $start_page && $t['p'] <= $end_page) {
                    $this->tableOfContents->_toc[$key]['p'] += ($target_page - $start_page);
                }
                if ($t['p'] >= $target_page && $t['p'] < $start_page) {
                    $this->tableOfContents->_toc[$key]['p'] += $n_toc;
                }
            }
        }

        // Update PageNumSubstitutions
        if (count($this->PageNumSubstitutions)) {
            $newarr = [];
            foreach ($this->PageNumSubstitutions as $k => $v) {
                if ($this->PageNumSubstitutions[$k]['from'] >= $start_page && $this->PageNumSubstitutions[$k]['from'] <= $end_page) {
                    $this->PageNumSubstitutions[$k]['from'] += ($target_page - $start_page);
                    $newarr[$this->PageNumSubstitutions[$k]['from']] = $this->PageNumSubstitutions[$k];
                } elseif ($this->PageNumSubstitutions[$k]['from'] >= $target_page && $this->PageNumSubstitutions[$k]['from'] < $start_page) {
                    $this->PageNumSubstitutions[$k]['from'] += $n_toc;
                    $newarr[$this->PageNumSubstitutions[$k]['from']] = $this->PageNumSubstitutions[$k];
                } else {
                    $newarr[$this->PageNumSubstitutions[$k]['from']] = $this->PageNumSubstitutions[$k];
                }
            }

            if (!$sp_present) {
                $newarr[$target_page] = ['from' => $target_page, 'suppress' => $sp_suppress, 'reset' => $sp_reset, 'type' => $sp_type];
            }
            if (!$tp_present) {
                $newarr[($target_page + $n_toc)] = ['from' => ($target_page + $n_toc), 'suppress' => $tp_suppress, 'reset' => $tp_reset, 'type' => $tp_type];
            }
            if (!$ep_present && $end_page > count($this->pages)) {
                $newarr[($end_page + 1)] = ['from' => ($end_page + 1), 'suppress' => $ep_suppress, 'reset' => $ep_reset, 'type' => $ep_type];
            }
            ksort($newarr);
            $this->PageNumSubstitutions = [];
            foreach ($newarr as $v) {
                $this->PageNumSubstitutions[] = $v;
            }
        }
    }

    public function DeletePages($start_page, $end_page = -1)
    {
        // move a page/pages EARLIER in the document
        if ($end_page < 1) {
            $end_page = $start_page;
        }
        $n_tod = $end_page - $start_page + 1;
        $last_page = count($this->pages);
        $n_atend = $last_page - $end_page + 1;

        // move pages
        for ($i = 0; $i < $n_atend; $i++) {
            $this->pages[$start_page + $i] = $this->pages[$end_page + 1 + $i];
        }
        // delete pages
        for ($i = 0; $i < $n_tod; $i++) {
            unset($this->pages[$last_page - $i]);
        }


        /* -- BOOKMARKS -- */
        // Update Bookmarks
        foreach ($this->BMoutlines as $i => $o) {
            if ($o['p'] >= $end_page) {
                $this->BMoutlines[$i]['p'] -= $n_tod;
            } elseif ($p < $start_page) {
                unset($this->BMoutlines[$i]);
            }
        }
        /* -- END BOOKMARKS -- */

        // Update Page Links
        if (count($this->PageLinks)) {
            $newarr = [];
            foreach ($this->PageLinks as $i => $o) {
                foreach ($this->PageLinks[$i] as $key => $pl) {
                    if (strpos($pl[4], '@') === 0) {
                        $p = substr($pl[4], 1);
                        if ($p > $end_page) {
                            $this->PageLinks[$i][$key][4] = '@' . ($p - $n_tod);
                        } elseif ($p < $start_page) {
                            unset($this->PageLinks[$i][$key]);
                        }
                    }
                }
                if ($i > $end_page) {
                    $newarr[($i - $n_tod)] = $this->PageLinks[$i];
                } elseif ($p < $start_page) {
                    $newarr[$i] = $this->PageLinks[$i];
                }
            }
            $this->PageLinks = $newarr;
        }

        // OrientationChanges
        if (count($this->OrientationChanges)) {
            $newarr = [];
            foreach ($this->OrientationChanges as $p => $v) {
                if ($p > $end_page) {
                    $newarr[($p - $t_tod)] = $this->OrientationChanges[$p];
                } elseif ($p < $start_page) {
                    $newarr[$p] = $this->OrientationChanges[$p];
                }
            }
            ksort($newarr);
            $this->OrientationChanges = $newarr;
        }

        // Page Dimensions
        if (count($this->pageDim)) {
            $newarr = [];
            foreach ($this->pageDim as $p => $v) {
                if ($p > $end_page) {
                    $newarr[($p - $n_tod)] = $this->pageDim[$p];
                } elseif ($p < $start_page) {
                    $newarr[$p] = $this->pageDim[$p];
                }
            }
            ksort($newarr);
            $this->pageDim = $newarr;
        }

        // HTML Headers & Footers
        if (count($this->saveHTMLHeader)) {
            foreach ($this->saveHTMLHeader as $p => $v) {
                if ($p > $end_page) {
                    $newarr[($p - $n_tod)] = $this->saveHTMLHeader[$p];
                } // mPDF 5.7.3
                elseif ($p < $start_page) {
                    $newarr[$p] = $this->saveHTMLHeader[$p];
                }
            }
            ksort($newarr);
            $this->saveHTMLHeader = $newarr;
        }
        if (count($this->saveHTMLFooter)) {
            $newarr = [];
            foreach ($this->saveHTMLFooter as $p => $v) {
                if ($p > $end_page) {
                    $newarr[($p - $n_tod)] = $this->saveHTMLFooter[$p];
                } elseif ($p < $start_page) {
                    $newarr[$p] = $this->saveHTMLFooter[$p];
                }
            }
            ksort($newarr);
            $this->saveHTMLFooter = $newarr;
        }

        // Update Internal Links
        foreach ($this->internallink as $key => $o) {
            if ($o['PAGE'] > $end_page) {
                $this->internallink[$key]['PAGE'] -= $n_tod;
            } elseif ($o['PAGE'] < $start_page) {
                unset($this->internallink[$key]);
            }
        }

        // Update Links
        foreach ($this->links as $key => $o) {
            if ($o[0] > $end_page) {
                $this->links[$key][0] -= $n_tod;
            } elseif ($o[0] < $start_page) {
                unset($this->links[$key]);
            }
        }

        // Update Form fields
        foreach ($this->form->forms as $key => $f) {
            if ($f['page'] > $end_page) {
                $this->form->forms[$key]['page'] -= $n_tod;
            } elseif ($f['page'] < $start_page) {
                unset($this->form->forms[$key]);
            }
        }

        /* -- ANNOTATIONS -- */
        // Update Annotations
        if (count($this->PageAnnots)) {
            $newarr = [];
            foreach ($this->PageAnnots as $p => $anno) {
                if ($p > $end_page) {
                    foreach ($anno as $o) {
                        $newarr[($p - $n_tod)][] = $o;
                    }
                } elseif ($p < $start_page) {
                    $newarr[$p] = $this->PageAnnots[$p];
                }
            }
            ksort($newarr);
            $this->PageAnnots = $newarr;
        }
        /* -- END ANNOTATIONS -- */

        // Update PageNumSubstitutions
        foreach ($this->PageNumSubstitutions as $k => $v) {
            if ($this->PageNumSubstitutions[$k]['from'] > $end_page) {
                $this->PageNumSubstitutions[$k]['from'] -= $n_tod;
            } elseif ($this->PageNumSubstitutions[$k]['from'] < $start_page) {
                unset($this->PageNumSubstitutions[$k]);
            }
        }

        unset($newarr);
        $this->page = count($this->pages);
    }

    // ======================================================
        /* -- INDEX -- */
    // FROM class PDF_Ref == INDEX

    public function IndexEntry($txt, $xref = '')
    {
        if ($xref) {
            $this->IndexEntrySee($txt, $xref);
            return;
        }

        // Search the reference (AND Ref/PageNo) in the array
        $Present = false;
        if ($this->keep_block_together) {
            // do nothing
        } /* -- TABLES -- */ elseif ($this->kwt) {
            $size = count($this->kwt_Reference);
            for ($i = 0; $i < $size; $i++) {
                if (isset($this->kwt_Reference[$i]['t']) && $this->kwt_Reference[$i]['t'] == $txt) {
                    $Present = true;
                    if ($this->page != $this->kwt_Reference[$i]['op']) {
                        $this->kwt_Reference[$i]['op'] = $this->page;
                    }
                }
            }
            if (!$Present) { // If not found, add it
                $this->kwt_Reference[] = ['t' => $txt, 'op' => $this->page];
            }
        } /* -- END TABLES -- */ else {
            $size = count($this->Reference);
            for ($i = 0; $i < $size; $i++) {
                if (isset($this->Reference[$i]['t']) && $this->Reference[$i]['t'] == $txt) {
                    $Present = true;
                    if (!in_array($this->page, $this->Reference[$i]['p'])) {
                        $this->Reference[$i]['p'][] = $this->page;
                    }
                }
            }
            if (!$Present) { // If not found, add it
                $this->Reference[] = ['t' => $txt, 'p' => [$this->page]];
            }
        }
    }

    // Added function to add a reference "Elephants. See Chickens"
    public function IndexEntrySee($txta, $txtb)
    {
        if ($this->directionality == 'rtl') { // *OTL*
            // ONLY DO THIS IF NOT IN TAGS
            if ($txta == strip_tags($txta)) {
                $txta = str_replace(':', ' - ', $txta); // *OTL*
            }
            if ($txtb == strip_tags($txtb)) {
                $txtb = str_replace(':', ' - ', $txtb); // *OTL*
            }
        } // *OTL*
        else { // *OTL*
            if ($txta == strip_tags($txta)) {
                $txta = str_replace(':', ', ', $txta);
            }
            if ($txtb == strip_tags($txtb)) {
                $txtb = str_replace(':', ', ', $txtb);
            }
        } // *OTL*
        $this->Reference[] = ['t' => $txta . ' - see ' . $txtb, 'p' => []];
    }

    public function InsertIndex($usedivletters = 1, $useLinking = false, $indexCollationLocale = '', $indexCollationGroup = '')
    {
        $size = count($this->Reference);
        if ($size == 0) {
            return false;
        }

        // $spacer used after named entry
        // $sep  separates number [groups], $joiner joins numbers in range
        //  e.g. "elephant 73, 97-99"  =  elephant[$spacer]73[$sep]97[$joiner]99
        // $subEntrySeparator separates main and subentry (if $this->indexUseSubentries == false;) e.g.
        // Mammal:elephant => Mammal[$subEntrySeparator]elephant
        // $subEntryInset specifies what precedes a subentry (if $this->indexUseSubentries == true;) e.g.
        // Mammal:elephant => [$subEntryInset]elephant
        $spacer = "\xc2\xa0 ";
        if ($this->directionality == 'rtl') {
            $sep = '&#x060c; ';
            $joiner = '-';
            $subEntrySeparator = '&#x060c; ';
            $subEntryInset = ' - ';
        } else {
            $sep = ', ';
            $joiner = '-';
            $subEntrySeparator = ', ';
            $subEntryInset = ' - ';
        }

        for ($i = 0; $i < $size; $i++) {
            $txt = $this->Reference[$i]['t'];
            $txt = strip_tags($txt); // mPDF 6
            $txt = $this->purify_utf8($txt);
            $this->Reference[$i]['uf'] = $txt; // Unformatted e.g. pure utf-8 encoded characters, no mark-up/tags
            // Used for ordering and collation
        }

        if ($usedivletters) {
            if ($indexCollationGroup) {
                $collation = require __DIR__ . '/../data/collations/' . $indexCollationGroup . '.php';
            } else {
                $collation = [];
            }
            for ($i = 0; $i < $size; $i++) {
                if ($this->Reference[$i]['uf']) {
                    $l = mb_substr($this->Reference[$i]['uf'], 0, 1, 'UTF-8');
                    if (isset($indexCollationGroup) && $indexCollationGroup) {
                        $uni = $this->UTF8StringToArray($l);
                        $ucode = $uni[0];
                        if (isset($collation[$ucode])) {
                            $this->Reference[$i]['d'] = UtfString::code2utf($collation[$ucode]);
                        } else {
                            $this->Reference[$i]['d'] = mb_strtolower($l, 'UTF-8');
                        }
                    } else {
                        $this->Reference[$i]['d'] = mb_strtolower($l, 'UTF-8');
                    }
                }
            }
        }

        // Alphabetic sort of the references
        $originalLocale = setlocale(LC_COLLATE, 0);
        if ($indexCollationLocale) {
            setlocale(LC_COLLATE, $indexCollationLocale);
        }

        usort($this->Reference, function ($a, $b) {
            return strcoll(strtolower($a['uf']), strtolower($b['uf']));
        });

        if ($indexCollationLocale) {
            setlocale(LC_COLLATE, $originalLocale);
        }

        $html = '<div class="mpdf_index_main">';

        $lett = '';
        $last_lett = '';
        $mainentry = '';
        for ($i = 0; $i < $size; $i++) {
            if ($this->Reference[$i]['t']) {
                if ($usedivletters) {
                    $lett = $this->Reference[$i]['d'];
                    if ($lett != $last_lett) {
                        $html .= '<div class="mpdf_index_letter">' . $lett . '</div>';
                    }
                }
                $txt = $this->Reference[$i]['t'];

                // Sub-entries e.g. Mammals:elephant
                // But allow for tags e.g. <b>Mammal</b>:elephants
                $a = preg_split('/(<.*?>)/', $txt, -1, PREG_SPLIT_DELIM_CAPTURE);
                $txt = '';
                $marker = false;
                foreach ($a as $k => $e) {
                    if ($k % 2 == 0 && !$marker) {
                        if (strpos($e, ':') !== false) { // == SubEntry
                            if ($this->indexUseSubentries) {
                                // If the Main entry does not have any page numbers associated with it
                                // create and insert an entry
                                list($txtmain, $sub) = preg_split('/[:]/', $e, 2);
                                if (strip_tags($txt . $txtmain) != $mainentry) {
                                    $html .= '<div class="mpdf_index_entry">' . $txt . $txtmain . '</div>';
                                    $mainentry = strip_tags($txt . $txtmain);
                                }

                                $txt = $subEntryInset;
                                $e = $sub; // Only replace first one
                            } else {
                                $e = preg_replace('/[:]/', $subEntrySeparator, $e, 1); // Only replace first one
                            }
                            $marker = true; // Don't replace any more once the subentry marker has been found
                        }
                    }
                    $txt .= $e;
                }

                if (!$marker) {
                    $mainentry = strip_tags($txt);
                }

                $html .= '<div class="mpdf_index_entry">';
                $html .= $txt;
                $ppp = $this->Reference[$i]['p']; // = array of page numbers to point to
                if (count($ppp)) {
                    sort($ppp);
                    $newarr = [];
                    $range_start = $ppp[0];
                    $range_end = 0;

                    $html .= $spacer;

                    for ($zi = 1; $zi < count($ppp); $zi++) {
                        if ($ppp[$zi] == ($ppp[($zi - 1)] + 1)) {
                            $range_end = $ppp[$zi];
                        } else {
                            if ($range_end) {
                                if ($range_end == $range_start + 1) {
                                    if ($useLinking) {
                                        $html .= '<a class="mpdf_index_link" href="@' . $range_start . '">';
                                    }
                                    $html .= $this->docPageNum($range_start);
                                    if ($useLinking) {
                                        $html .= '</a>';
                                    }
                                    $html .= $sep;

                                    if ($useLinking) {
                                        $html .= '<a class="mpdf_index_link" href="@' . $ppp[$zi - 1] . '">';
                                    }
                                    $html .= $this->docPageNum($ppp[$zi - 1]);
                                    if ($useLinking) {
                                        $html .= '</a>';
                                    }
                                    $html .= $sep;
                                }
                            } else {
                                if ($useLinking) {
                                    $html .= '<a class="mpdf_index_link" href="@' . $ppp[$zi - 1] . '">';
                                }
                                $html .= $this->docPageNum($ppp[$zi - 1]);
                                if ($useLinking) {
                                    $html .= '</a>';
                                }
                                $html .= $sep;
                            }
                            $range_start = $ppp[$zi];
                            $range_end = 0;
                        }
                    }

                    if ($range_end) {
                        if ($useLinking) {
                            $html .= '<a class="mpdf_index_link" href="@' . $range_start . '">';
                        }
                        $html .= $this->docPageNum($range_start);
                        if ($range_end == $range_start + 1) {
                            if ($useLinking) {
                                $html .= '</a>';
                            }
                            $html .= $sep;
                            if ($useLinking) {
                                $html .= '<a class="mpdf_index_link" href="@' . $range_end . '">';
                            }
                            $html .= $this->docPageNum($range_end);
                            if ($useLinking) {
                                $html .= '</a>';
                            }
                        } else {
                            $html .= $joiner;
                            $html .= $this->docPageNum($range_end);
                            if ($useLinking) {
                                $html .= '</a>';
                            }
                        }
                    } else {
                        if ($useLinking) {
                            $html .= '<a class="mpdf_index_link" href="@' . $ppp[(count($ppp) - 1)] . '">';
                        }
                        $html .= $this->docPageNum($ppp[(count($ppp) - 1)]);
                        if ($useLinking) {
                            $html .= '</a>';
                        }
                    }
                }
            }
            $html .= '</div>';
            $last_lett = $lett;
        }
        $html .= '</div>';
        $save_fpb = $this->fixedPosBlockSave;
        $this->WriteHTML($html);
        $this->fixedPosBlockSave = $save_fpb;

        $this->breakpoints[$this->CurrCol][] = $this->y;  // *COLUMNS*
    }

    /* -- END INDEX -- */

    public function AcceptPageBreak()
    {
        if (count($this->cellBorderBuffer)) {
            $this->printcellbuffer();
        } // *TABLES*
        /* -- COLUMNS -- */
        if ($this->ColActive == 1) {
            if ($this->CurrCol < $this->NbCol - 1) {
                // Go to the next column
                $this->CurrCol++;
                $this->SetCol($this->CurrCol);
                $this->y = $this->y0;
                $this->ChangeColumn = 1; // Number (and direction) of columns changed +1, +2, -2 etc.
                // DIRECTIONALITY RTL
                if ($this->directionality == 'rtl') {
                    $this->ChangeColumn = -($this->ChangeColumn);
                } // *OTL*
                // Stay on the page
                return false;
            } else {
                // Go back to the first column - NEW PAGE
                if (count($this->columnbuffer)) {
                    $this->printcolumnbuffer();
                }
                $this->SetCol(0);
                $this->y0 = $this->tMargin;
                $this->ChangeColumn = -($this->NbCol - 1);
                // DIRECTIONALITY RTL
                if ($this->directionality == 'rtl') {
                    $this->ChangeColumn = -($this->ChangeColumn);
                } // *OTL*
                // Page break
                return true;
            }
        } /* -- END COLUMNS -- */
        /* -- TABLES -- */ elseif ($this->table_rotate) {
            if ($this->tablebuffer) {
                $this->printtablebuffer();
            }
            return true;
        } /* -- END TABLES -- */ else { // *COLUMNS*
            $this->ChangeColumn = 0;
            return $this->autoPageBreak;
        } // *COLUMNS*
        return $this->autoPageBreak;
    }

    // ----------- COLUMNS ---------------------
    /* -- COLUMNS -- */

    public function SetColumns($NbCol, $vAlign = '', $gap = 5)
    {
        // NbCol = number of columns
        // Anything less than 2 turns columns off
        if ($NbCol < 2) { // SET COLUMNS OFF
            if ($this->ColActive) {
                $this->ColActive = 0;
                if (count($this->columnbuffer)) {
                    $this->printcolumnbuffer();
                }
                $this->NbCol = 1;
                $this->ResetMargins();
                $this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
                $this->divwidth = 0;
                $this->Ln();
            }
            $this->ColActive = 0;
            $this->columnbuffer = [];
            $this->ColDetails = [];
            $this->columnLinks = [];
            $this->columnAnnots = [];
            $this->columnForms = [];
            $this->col_BMoutlines = [];
            $this->col_toc = [];
            $this->breakpoints = [];
        } else { // SET COLUMNS ON
            if ($this->ColActive) {
                $this->ColActive = 0;
                if (count($this->columnbuffer)) {
                    $this->printcolumnbuffer();
                }
                $this->ResetMargins();
            }
            if (isset($this->y) && $this->y > $this->tMargin) {
                $this->Ln();
            }
            $this->NbCol = $NbCol;
            $this->ColGap = $gap;
            $this->divwidth = 0;
            $this->ColActive = 1;
            $this->ColumnAdjust = true; // enables column height adjustment for the page
            $this->columnbuffer = [];
            $this->ColDetails = [];
            $this->columnLinks = [];
            $this->columnAnnots = [];
            $this->columnForms = [];
            $this->col_BMoutlines = [];
            $this->col_toc = [];
            $this->breakpoints = [];
            if ((strtoupper($vAlign) == 'J') || (strtoupper($vAlign) == 'JUSTIFY')) {
                $vAlign = 'J';
            } else {
                $vAlign = '';
            }
            $this->colvAlign = $vAlign;
            // Save the ordinate
            $absL = $this->DeflMargin - ($gap / 2);
            $absR = $this->DefrMargin - ($gap / 2);
            $PageWidth = $this->w - $absL - $absR; // virtual pagewidth for calculation only
            $ColWidth = (($PageWidth - ($gap * ($NbCol))) / $NbCol);
            $this->ColWidth = $ColWidth;
            /* -- OTL -- */

            if ($this->directionality == 'rtl') {
                for ($i = 0; $i < $this->NbCol; $i++) {
                    $this->ColL[$i] = $absL + ($gap / 2) + (($NbCol - ($i + 1)) * ($PageWidth / $NbCol));
                    $this->ColR[$i] = $this->ColL[$i] + $ColWidth; // NB This is not R margin -> R pos
                }
            } else {
                /* -- END OTL -- */
                for ($i = 0; $i < $this->NbCol; $i++) {
                    $this->ColL[$i] = $absL + ($gap / 2) + ($i * ($PageWidth / $NbCol) );
                    $this->ColR[$i] = $this->ColL[$i] + $ColWidth; // NB This is not R margin -> R pos
                }
            } // *OTL*
            $this->pgwidth = $ColWidth;
            $this->SetCol(0);
            $this->y0 = $this->y;
        }
        $this->x = $this->lMargin;
    }

    public function SetCol($CurrCol)
    {
        // Used internally to set column by number: 0 is 1st column
        // Set position on a column
        $this->CurrCol = $CurrCol;
        $x = $this->ColL[$CurrCol];
        $xR = $this->ColR[$CurrCol]; // NB This is not R margin -> R pos
        if (($this->mirrorMargins) && (($this->page) % 2 == 0)) { // EVEN
            $x += $this->MarginCorrection;
            $xR += $this->MarginCorrection;
        }
        $this->SetMargins($x, ($this->w - $xR), $this->tMargin);
    }

    public function AddColumn()
    {
        $this->NewColumn();
        $this->ColumnAdjust = false; // disables all column height adjustment for the page.
    }

    public function NewColumn()
    {
        if ($this->ColActive == 1) {
            if ($this->CurrCol < $this->NbCol - 1) {
                // Go to the next column
                $this->CurrCol++;
                $this->SetCol($this->CurrCol);
                $this->y = $this->y0;
                $this->ChangeColumn = 1;
                // DIRECTIONALITY RTL
                if ($this->directionality == 'rtl') {
                    $this->ChangeColumn = -($this->ChangeColumn);
                } // *OTL*
                // Stay on the page
            } else {
                // Go back to the first column
                // Page break
                if (count($this->columnbuffer)) {
                    $this->printcolumnbuffer();
                }
                $this->AddPage($this->CurOrientation);
                $this->SetCol(0);
                $this->y0 = $this->tMargin;
                $this->ChangeColumn = -($this->NbCol - 1);
                // DIRECTIONALITY RTL
                if ($this->directionality == 'rtl') {
                    $this->ChangeColumn = -($this->ChangeColumn);
                } // *OTL*
            }
            $this->x = $this->lMargin;
        } else {
            $this->AddPage($this->CurOrientation);
        }
    }

    public function printcolumnbuffer()
    {
        // Columns ended (but page not ended) -> try to match all columns - unless disabled by using a custom column-break
        if (!$this->ColActive && $this->ColumnAdjust && !$this->keepColumns) {
            // Calculate adjustment to add to each column to calculate rel_y value
            $this->ColDetails[0]['add_y'] = 0;
            $last_col = 0;
            // Recursively add previous column's height
            for ($i = 1; $i < $this->NbCol; $i++) {
                if (isset($this->ColDetails[$i]['bottom_margin']) && $this->ColDetails[$i]['bottom_margin']) { // If any entries in the column
                    $this->ColDetails[$i]['add_y'] = ($this->ColDetails[$i - 1]['bottom_margin'] - $this->y0) + $this->ColDetails[$i - 1]['add_y'];
                    $last_col = $i;  // Last column actually printed
                }
            }

            // Calculate value for each position sensitive entry as though for one column
            foreach ($this->columnbuffer as $key => $s) {
                $t = $s['s'];
                if ($t == 'ACROFORM') {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                    $this->columnbuffer[$key]['s'] = '';
                } elseif (preg_match('/BT \d+\.\d\d+ (\d+\.\d\d+) Td/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                } elseif (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ [\-]{0,1}\d+\.\d\d+ re/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                } elseif (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) m/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                } elseif (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) l/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                } elseif (preg_match('/q \d+\.\d\d+ 0 0 \d+\.\d\d+ \d+\.\d\d+ (\d+\.\d\d+) cm \/(I|FO)\d+ Do Q/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                } elseif (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ c/', $t)) {
                    $this->columnbuffer[$key]['rel_y'] = $s['y'] + $this->ColDetails[$s['col']]['add_y'] - $this->y0;
                }
            }
            foreach ($this->internallink as $key => $f) {
                if (is_array($f) && isset($f['col'])) {
                    $this->internallink[$key]['rel_y'] = $f['Y'] + $this->ColDetails[$f['col']]['add_y'] - $this->y0;
                }
            }

            $breaks = [];
            foreach ($this->breakpoints as $c => $bpa) {
                foreach ($bpa as $rely) {
                    $breaks[] = $rely + $this->ColDetails[$c]['add_y'] - $this->y0;
                }
            }


            if (isset($this->ColDetails[$last_col]['bottom_margin'])) {
                $lcbm = $this->ColDetails[$last_col]['bottom_margin'];
            } else {
                $lcbm = 0;
            }
            $sum_h = $this->ColDetails[$last_col]['add_y'] + $lcbm - $this->y0;
            // $sum_h = max($this->ColDetails[$last_col]['add_y'] + $this->ColDetails[$last_col]['bottom_margin'] - $this->y0, end($breaks));
            $target_h = ($sum_h / $this->NbCol);

            $cbr = [];
            for ($i = 1; $i < $this->NbCol; $i++) {
                $th = ($sum_h * $i / $this->NbCol);
                foreach ($breaks as $bk => $val) {
                    if ($val > $th) {
                        if (($val - $th) < ($th - $breaks[$bk - 1])) {
                            $cbr[$i - 1] = $val;
                        } else {
                            $cbr[$i - 1] = $breaks[$bk - 1];
                        }
                        break;
                    }
                }
            }
            $cbr[($this->NbCol - 1)] = $sum_h;

            // mPDF 6
            // Avoid outputing with 1st column empty
            if (isset($cbr[0]) && $cbr[0] == 0) {
                for ($i = 0; $i < $this->NbCol - 1; $i++) {
                    $cbr[$i] = $cbr[$i + 1];
                }
            }

            // Now update the columns - divide into columns of approximately equal value
            $last_new_col = 0;
            $yadj = 0; // mm
            $xadj = 0;
            $last_col_bottom = 0;
            $lowest_bottom_y = 0;
            $block_bottom = 0;
            $newcolumn = 0;
            foreach ($this->columnbuffer as $key => $s) {
                if (isset($s['rel_y'])) { // only process position sensitive data
                    if ($s['rel_y'] >= $cbr[$newcolumn]) {
                        $newcolumn++;
                    } else {
                        $newcolumn = $last_new_col;
                    }


                    $block_bottom = max($block_bottom, ($s['rel_y'] + $s['h']));

                    if ($this->directionality == 'rtl') { // *OTL*
                        $xadj = -(($newcolumn - $s['col']) * ($this->ColWidth + $this->ColGap)); // *OTL*
                    } // *OTL*
                    else { // *OTL*
                        $xadj = ($newcolumn - $s['col']) * ($this->ColWidth + $this->ColGap);
                    } // *OTL*

                    if ($last_new_col != $newcolumn) { // Added new column
                        $last_col_bottom = $this->columnbuffer[$key]['rel_y'];
                        $block_bottom = 0;
                    }
                    $yadj = ($s['rel_y'] - $s['y']) - ($last_col_bottom) + $this->y0;
                    // callback function
                    $t = $s['s'];

                    // mPDF 5.7+
                    $t = $this->columnAdjustPregReplace('Td', $xadj, $yadj, '/BT (\d+\.\d\d+) (\d+\.\d\d+) Td/', $t);
                    $t = $this->columnAdjustPregReplace('re', $xadj, $yadj, '/(\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) ([\-]{0,1}\d+\.\d\d+) re/', $t);
                    $t = $this->columnAdjustPregReplace('l', $xadj, $yadj, '/(\d+\.\d\d+) (\d+\.\d\d+) l/', $t);
                    $t = $this->columnAdjustPregReplace('img', $xadj, $yadj, '/q (\d+\.\d\d+) 0 0 (\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) cm \/(I|FO)/', $t);
                    $t = $this->columnAdjustPregReplace('draw', $xadj, $yadj, '/(\d+\.\d\d+) (\d+\.\d\d+) m/', $t);
                    $t = $this->columnAdjustPregReplace('bezier', $xadj, $yadj, '/(\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) (\d+\.\d\d+) c/', $t);

                    $this->columnbuffer[$key]['s'] = $t;
                    $this->columnbuffer[$key]['newcol'] = $newcolumn;
                    $this->columnbuffer[$key]['newy'] = $s['y'] + $yadj;
                    $last_new_col = $newcolumn;
                    $clb = $s['y'] + $yadj + $s['h']; // bottom_margin of current
                    if ((isset($this->ColDetails[$newcolumn]['max_bottom']) && $clb > $this->ColDetails[$newcolumn]['max_bottom']) || (!isset($this->ColDetails[$newcolumn]['max_bottom']) && $clb)) {
                        $this->ColDetails[$newcolumn]['max_bottom'] = $clb;
                    }
                    if ($clb > $lowest_bottom_y) {
                        $lowest_bottom_y = $clb;
                    }
                    // Adjust LINKS
                    if (isset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])])) {
                        $ref = $this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])];
                        $this->PageLinks[$this->page][$ref][0] += ($xadj * Mpdf::SCALE);
                        $this->PageLinks[$this->page][$ref][1] -= ($yadj * Mpdf::SCALE);
                        unset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])]);
                    }
                    // Adjust FORM FIELDS
                    if (isset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])])) {
                        $ref = $this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])];
                        $this->form->forms[$ref]['x'] += ($xadj);
                        $this->form->forms[$ref]['y'] += ($yadj);
                        unset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])]);
                    }
                    /* -- ANNOTATIONS -- */
                    if (isset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])])) {
                        $ref = $this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])];
                        if ($this->PageAnnots[$this->page][$ref]['x'] < 0) {
                            $this->PageAnnots[$this->page][$ref]['x'] -= ($xadj);
                        } else {
                            $this->PageAnnots[$this->page][$ref]['x'] += ($xadj);
                        }
                        $this->PageAnnots[$this->page][$ref]['y'] += ($yadj); // unlike PageLinks, Page annots has y values from top in mm
                        unset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])]);
                    }
                    /* -- END ANNOTATIONS -- */
                }
            }

            /* -- BOOKMARKS -- */
            // Adjust Bookmarks
            foreach ($this->col_BMoutlines as $v) {
                $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $this->y0, 'p' => $v['p']];
            }
            /* -- END BOOKMARKS -- */

            /* -- TOC -- */

            // Adjust ToC
            foreach ($this->col_toc as $v) {
                $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $v['p'], 'link' => $v['link'], 'toc_id' => $v['toc_id']];
                $this->links[$v['link']][1] = $this->y0;
            }
            /* -- END TOC -- */

            // Adjust column length to be equal
            if ($this->colvAlign == 'J') {
                foreach ($this->columnbuffer as $key => $s) {
                    if (isset($s['rel_y'])) { // only process position sensitive data
                        // Set ratio to expand y values or heights
                        if (isset($this->ColDetails[$s['newcol']]['max_bottom']) && $this->ColDetails[$s['newcol']]['max_bottom'] && $this->ColDetails[$s['newcol']]['max_bottom'] != $this->y0) {
                            $ratio = ($lowest_bottom_y - ($this->y0)) / ($this->ColDetails[$s['newcol']]['max_bottom'] - ($this->y0));
                        } else {
                            $ratio = 1;
                        }
                        if (($ratio > 1) && ($ratio <= $this->max_colH_correction)) {
                            $yadj = ($s['newy'] - $this->y0) * ($ratio - 1);

                            // Adjust LINKS
                            if (isset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])])) {
                                $ref = $this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])];
                                $this->PageLinks[$this->page][$ref][1] -= ($yadj * Mpdf::SCALE); // y value
                                $this->PageLinks[$this->page][$ref][3] *= $ratio; // height
                                unset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])]);
                            }
                            // Adjust FORM FIELDS
                            if (isset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])])) {
                                $ref = $this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])];
                                $this->form->forms[$ref]['x'] += ($xadj);
                                $this->form->forms[$ref]['y'] += ($yadj);
                                unset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])]);
                            }
                            /* -- ANNOTATIONS -- */
                            if (isset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])])) {
                                $ref = $this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])];
                                $this->PageAnnots[$this->page][$ref]['y'] += ($yadj);
                                unset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])]);
                            }
                            /* -- END ANNOTATIONS -- */
                        }
                    }
                }
                foreach ($this->internallink as $key => $f) {
                    if (is_array($f) && isset($f['col'])) {
                        $last_col_bottom = 0;
                        for ($nbc = 0; $nbc < $this->NbCol; $nbc++) {
                            if ($f['rel_y'] >= $cbr[$nbc]) {
                                $last_col_bottom = $cbr[$nbc];
                            }
                        }
                        $yadj = ($f['rel_y'] - $f['Y']) - $last_col_bottom + $this->y0;
                        $f['Y'] += $yadj;
                        unset($f['col']);
                        unset($f['rel_y']);
                        $this->internallink[$key] = $f;
                    }
                }

                $last_col = -1;
                $trans_on = false;
                foreach ($this->columnbuffer as $key => $s) {
                    if (isset($s['rel_y'])) { // only process position sensitive data
                        // Set ratio to expand y values or heights
                        if (isset($this->ColDetails[$s['newcol']]['max_bottom']) && $this->ColDetails[$s['newcol']]['max_bottom'] && $this->ColDetails[$s['newcol']]['max_bottom'] != $this->y0) {
                            $ratio = ($lowest_bottom_y - ($this->y0)) / ($this->ColDetails[$s['newcol']]['max_bottom'] - ($this->y0));
                        } else {
                            $ratio = 1;
                        }
                        if (($ratio > 1) && ($ratio <= $this->max_colH_correction)) {
                            // Start Transformation
                            $this->pages[$this->page] .= $this->StartTransform(true) . "\n";
                            $this->pages[$this->page] .= $this->transformScale(100, $ratio * 100, $x = '', $this->y0, true) . "\n";
                            $trans_on = true;
                        }
                    }
                    // Now output the adjusted values
                    $this->pages[$this->page] .= $s['s'] . "\n";
                    if (isset($s['rel_y']) && ($ratio > 1) && ($ratio <= $this->max_colH_correction)) { // only process position sensitive data
                        // Stop Transformation
                        $this->pages[$this->page] .= $this->StopTransform(true) . "\n";
                        $trans_on = false;
                    }
                }
                if ($trans_on) {
                    $this->pages[$this->page] .= $this->StopTransform(true) . "\n";
                }
            } else { // if NOT $this->colvAlign == 'J'
                // Now output the adjusted values
                foreach ($this->columnbuffer as $s) {
                    $this->pages[$this->page] .= $s['s'] . "\n";
                }
            }
            if ($lowest_bottom_y > 0) {
                $this->y = $lowest_bottom_y;
            }
        } // Columns not ended but new page -> align columns (can leave the columns alone - just tidy up the height)
        elseif ($this->colvAlign == 'J' && $this->ColumnAdjust && !$this->keepColumns) {
            // calculate the lowest bottom margin
            $lowest_bottom_y = 0;
            foreach ($this->columnbuffer as $key => $s) {
                // Only process output data
                $t = $s['s'];
                if ($t == 'ACROFORM' || (preg_match('/BT \d+\.\d\d+ (\d+\.\d\d+) Td/', $t)) || (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ [\-]{0,1}\d+\.\d\d+ re/', $t)) ||
                    (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) l/', $t)) ||
                    (preg_match('/q \d+\.\d\d+ 0 0 \d+\.\d\d+ \d+\.\d\d+ (\d+\.\d\d+) cm \/(I|FO)\d+ Do Q/', $t)) ||
                    (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) m/', $t)) ||
                    (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ c/', $t))) {
                    $clb = $s['y'] + $s['h'];
                    if ((isset($this->ColDetails[$s['col']]['max_bottom']) && $clb > $this->ColDetails[$s['col']]['max_bottom']) || !isset($this->ColDetails[$s['col']]['max_bottom'])) {
                        $this->ColDetails[$s['col']]['max_bottom'] = $clb;
                    }
                    if ($clb > $lowest_bottom_y) {
                        $lowest_bottom_y = $clb;
                    }
                    $this->columnbuffer[$key]['rel_y'] = $s['y']; // Marks position sensitive data to process later
                    if ($t == 'ACROFORM') {
                        $this->columnbuffer[$key]['s'] = '';
                    }
                }
            }
            // Adjust column length equal
            foreach ($this->columnbuffer as $key => $s) {
                // Set ratio to expand y values or heights
                if (isset($this->ColDetails[$s['col']]['max_bottom']) && $this->ColDetails[$s['col']]['max_bottom']) {
                    $ratio = ($lowest_bottom_y - ($this->y0)) / ($this->ColDetails[$s['col']]['max_bottom'] - ($this->y0));
                } else {
                    $ratio = 1;
                }
                if (($ratio > 1) && ($ratio <= $this->max_colH_correction)) {
                    $yadj = ($s['y'] - $this->y0) * ($ratio - 1);

                    // Adjust LINKS
                    if (isset($s['rel_y'])) { // only process position sensitive data
                        // otherwise triggers for all entries in column buffer (.e.g. formatting) and makes below adjustments more than once
                        if (isset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])])) {
                            $ref = $this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])];
                            $this->PageLinks[$this->page][$ref][1] -= ($yadj * Mpdf::SCALE); // y value
                            $this->PageLinks[$this->page][$ref][3] *= $ratio; // height
                            unset($this->columnLinks[$s['col']][intval($s['x'])][intval($s['y'])]);
                        }
                        // Adjust FORM FIELDS
                        if (isset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])])) {
                            $ref = $this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])];
                            $this->form->forms[$ref]['x'] += ($xadj);
                            $this->form->forms[$ref]['y'] += ($yadj);
                            unset($this->columnForms[$s['col']][intval($s['x'])][intval($s['y'])]);
                        }
                        /* -- ANNOTATIONS -- */
                        if (isset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])])) {
                            $ref = $this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])];
                            $this->PageAnnots[$this->page][$ref]['y'] += ($yadj);
                            unset($this->columnAnnots[$s['col']][intval($s['x'])][intval($s['y'])]);
                        }
                        /* -- END ANNOTATIONS -- */
                    }
                }
            }

            /* -- BOOKMARKS -- */

            // Adjust Bookmarks
            foreach ($this->col_BMoutlines as $v) {
                $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $this->y0, 'p' => $v['p']];
            }
            /* -- END BOOKMARKS -- */

            /* -- TOC -- */

            // Adjust ToC
            foreach ($this->col_toc as $v) {
                $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $v['p'], 'link' => $v['link'], 'toc_id' => $v['toc_id']];
                $this->links[$v['link']][1] = $this->y0;
            }
            /* -- END TOC -- */
            $trans_on = false;
            foreach ($this->columnbuffer as $key => $s) {
                if (isset($s['rel_y'])) { // only process position sensitive data
                    // Set ratio to expand y values or heights
                    if ($this->ColDetails[$s['col']]['max_bottom']) {
                        $ratio = ($lowest_bottom_y - ($this->y0)) / ($this->ColDetails[$s['col']]['max_bottom'] - ($this->y0));
                    } else {
                        $ratio = 1;
                    }
                    if (($ratio > 1) && ($ratio <= $this->max_colH_correction)) {
                        // Start Transformation
                        $this->pages[$this->page] .= $this->StartTransform(true) . "\n";
                        $this->pages[$this->page] .= $this->transformScale(100, $ratio * 100, $x = '', $this->y0, true) . "\n";
                        $trans_on = true;
                    }
                }
                // Now output the adjusted values
                $this->pages[$this->page] .= $s['s'] . "\n";
                if (isset($s['rel_y']) && ($ratio > 1) && ($ratio <= $this->max_colH_correction)) {
                    // Stop Transformation
                    $this->pages[$this->page] .= $this->StopTransform(true) . "\n";
                    $trans_on = false;
                }
            }
            if ($trans_on) {
                $this->pages[$this->page] .= $this->StopTransform(true) . "\n";
            }

            if ($lowest_bottom_y > 0) {
                $this->y = $lowest_bottom_y;
            }
        } // Just reproduce the page as it was
        else {
            // If page has not ended but height adjustment was disabled by custom column-break - adjust y
            $lowest_bottom_y = 0;
            if (!$this->ColActive && (!$this->ColumnAdjust || $this->keepColumns)) {
                // calculate the lowest bottom margin
                foreach ($this->columnbuffer as $key => $s) {
                    // Only process output data
                    $t = $s['s'];
                    if ($t == 'ACROFORM' || (preg_match('/BT \d+\.\d\d+ (\d+\.\d\d+) Td/', $t)) || (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ [\-]{0,1}\d+\.\d\d+ re/', $t)) ||
                        (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) l/', $t)) ||
                        (preg_match('/q \d+\.\d\d+ 0 0 \d+\.\d\d+ \d+\.\d\d+ (\d+\.\d\d+) cm \/(I|FO)\d+ Do Q/', $t)) ||
                        (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) m/', $t)) ||
                        (preg_match('/\d+\.\d\d+ (\d+\.\d\d+) \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ c/', $t))) {
                        $clb = $s['y'] + $s['h'];
                        if ($clb > $this->ColDetails[$s['col']]['max_bottom']) {
                            $this->ColDetails[$s['col']]['max_bottom'] = $clb;
                        }
                        if ($clb > $lowest_bottom_y) {
                            $lowest_bottom_y = $clb;
                        }
                    }
                }
            }
            foreach ($this->columnbuffer as $key => $s) {
                if ($s['s'] != 'ACROFORM') {
                    $this->pages[$this->page] .= $s['s'] . "\n";
                }
            }
            if ($lowest_bottom_y > 0) {
                $this->y = $lowest_bottom_y;
            }
            /* -- BOOKMARKS -- */
            // Output Bookmarks
            foreach ($this->col_BMoutlines as $v) {
                $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $v['y'], 'p' => $v['p']];
            }
            /* -- END BOOKMARKS -- */
            /* -- TOC -- */
            // Output ToC
            foreach ($this->col_toc as $v) {
                $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $v['p'], 'link' => $v['link'], 'toc_id' => $v['toc_id']];
            }
            /* -- END TOC -- */
        }
        foreach ($this->internallink as $key => $f) {
            if (isset($this->internallink[$key]['col'])) {
                unset($this->internallink[$key]['col']);
            }
            if (isset($this->internallink[$key]['rel_y'])) {
                unset($this->internallink[$key]['rel_y']);
            }
        }

        $this->columnbuffer = [];
        $this->ColDetails = [];
        $this->columnLinks = [];
        $this->columnAnnots = [];
        $this->columnForms = [];

        $this->col_BMoutlines = [];
        $this->col_toc = [];
        $this->breakpoints = [];
    }

    // mPDF 5.7+
    public function columnAdjustPregReplace($type, $xadj, $yadj, $pattern, $subject)
    {
        preg_match($pattern, $subject, $matches);
        if (!count($matches)) {
            return $subject;
        }
        if (!isset($matches[3])) {
            $matches[3] = 0;
        }
        if (!isset($matches[4])) {
            $matches[4] = 0;
        }
        if (!isset($matches[5])) {
            $matches[5] = 0;
        }
        if (!isset($matches[6])) {
            $matches[6] = 0;
        }
        return str_replace($matches[0], $this->columnAdjustAdd($type, Mpdf::SCALE, $xadj, $yadj, $matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]), $subject);
    }

    /* -- END COLUMNS -- */

    // ==================================================================
    /* -- TABLES -- */
    public function printcellbuffer()
    {
        if (count($this->cellBorderBuffer)) {
            sort($this->cellBorderBuffer);
            foreach ($this->cellBorderBuffer as $cbb) {
                $cba = unpack("A16dom/nbord/A1side/ns/dbw/a6ca/A10style/dx/dy/dw/dh/dmbl/dmbr/dmrt/dmrb/dmtl/dmtr/dmlt/dmlb/dcpd/dover/", $cbb);
                $side = $cba['side'];
                $color = str_pad($cba['ca'], 6, "\x00");
                $details = [];
                $details[$side]['dom'] = (float) $cba['dom'];
                $details[$side]['s'] = $cba['s'];
                $details[$side]['w'] = $cba['bw'];
                $details[$side]['c'] = $color;
                $details[$side]['style'] = trim($cba['style']);
                $details['mbw']['BL'] = $cba['mbl'];
                $details['mbw']['BR'] = $cba['mbr'];
                $details['mbw']['RT'] = $cba['mrt'];
                $details['mbw']['RB'] = $cba['mrb'];
                $details['mbw']['TL'] = $cba['mtl'];
                $details['mbw']['TR'] = $cba['mtr'];
                $details['mbw']['LT'] = $cba['mlt'];
                $details['mbw']['LB'] = $cba['mlb'];
                $details['cellposdom'] = $cba['cpd'];
                $details['p'] = $side;
                if ($cba['over'] == 1) {
                    $details[$side]['overlay'] = true;
                } else {
                    $details[$side]['overlay'] = false;
                }
                $this->_tableRect($cba['x'], $cba['y'], $cba['w'], $cba['h'], $cba['bord'], $details, false, false);
            }
            $this->cellBorderBuffer = [];
        }
    }

    // ==================================================================
    public function printtablebuffer()
    {

        if (!$this->table_rotate) {
            $this->pages[$this->page] .= $this->tablebuffer;
            foreach ($this->tbrot_Links as $p => $l) {
                foreach ($l as $v) {
                    $this->PageLinks[$p][] = $v;
                }
            }
            $this->tbrot_Links = [];
            /* -- ANNOTATIONS -- */
            foreach ($this->tbrot_Annots as $p => $l) {
                foreach ($l as $v) {
                    $this->PageAnnots[$p][] = $v;
                }
            }
            $this->tbrot_Annots = [];
            /* -- END ANNOTATIONS -- */

            /* -- BOOKMARKS -- */
            // Output Bookmarks
            foreach ($this->tbrot_BMoutlines as $v) {
                $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $v['y'], 'p' => $v['p']];
            }
            $this->tbrot_BMoutlines = [];
            /* -- END BOOKMARKS -- */

            /* -- TOC -- */
            // Output ToC
            foreach ($this->tbrot_toc as $v) {
                $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $v['p'], 'link' => $v['link'], 'toc_id' => $v['toc_id']];
            }
            $this->tbrot_toc = [];
            /* -- END TOC -- */

            return;
        }
        // elseif rotated
        $lm = $this->lMargin + $this->blk[$this->blklvl]['outer_left_margin'] + $this->blk[$this->blklvl]['border_left']['w'] + $this->blk[$this->blklvl]['padding_left'];
        $pw = $this->blk[$this->blklvl]['inner_width'];
        // Start Transformation
        $this->pages[$this->page] .= $this->StartTransform(true) . "\n";

        if ($this->table_rotate > 1) { // clockwise
            if ($this->tbrot_align == 'L') {
                $xadj = $this->tbrot_h; // align L (as is)
            } elseif ($this->tbrot_align == 'R') {
                $xadj = $lm - $this->tbrot_x0 + ($pw); // align R
            } else {
                $xadj = $lm - $this->tbrot_x0 + (($pw + $this->tbrot_h) / 2); // align C
            }
            $yadj = 0;
        } else { // anti-clockwise
            if ($this->tbrot_align == 'L') {
                $xadj = 0; // align L (as is)
            } elseif ($this->tbrot_align == 'R') {
                $xadj = $lm - $this->tbrot_x0 + ($pw - $this->tbrot_h); // align R
            } else {
                $xadj = $lm - $this->tbrot_x0 + (($pw - $this->tbrot_h) / 2); // align C
            }
            $yadj = $this->tbrot_w;
        }


        $this->pages[$this->page] .= $this->transformTranslate($xadj, $yadj, true) . "\n";
        $this->pages[$this->page] .= $this->transformRotate($this->table_rotate, $this->tbrot_x0, $this->tbrot_y0, true) . "\n";

        // Now output the adjusted values
        $this->pages[$this->page] .= $this->tablebuffer;


        foreach ($this->tbrot_Links as $p => $l) {
            foreach ($l as $v) {
                $w = $v[2] / Mpdf::SCALE;
                $h = $v[3] / Mpdf::SCALE;
                $ax = ($v[0] / Mpdf::SCALE) - $this->tbrot_x0;
                $ay = (($this->hPt - $v[1]) / Mpdf::SCALE) - $this->tbrot_y0;
                if ($this->table_rotate > 1) { // clockwise
                    $bx = $this->tbrot_x0 + $xadj - $ay - $h;
                    $by = $this->tbrot_y0 + $yadj + $ax;
                } else {
                    $bx = $this->tbrot_x0 + $xadj + $ay;
                    $by = $this->tbrot_y0 + $yadj - $ax - $w;
                }
                $v[0] = $bx * Mpdf::SCALE;
                $v[1] = ($this->h - $by) * Mpdf::SCALE;
                $v[2] = $h * Mpdf::SCALE; // swap width and height
                $v[3] = $w * Mpdf::SCALE;
                $this->PageLinks[$p][] = $v;
            }
        }
        $this->tbrot_Links = [];
        foreach ($this->internallink as $key => $f) {
            if (is_array($f) && isset($f['tbrot'])) {
                $f['Y'] = $this->tbrot_y0;
                $f['PAGE'] = $this->page;
                unset($f['tbrot']);
                $this->internallink[$key] = $f;
            }
        }
        /* -- ANNOTATIONS -- */
        foreach ($this->tbrot_Annots as $p => $l) {
            foreach ($l as $v) {
                $ax = abs($v['x']) - $this->tbrot_x0; // abs because -ve values are internally set and held for reference if annotMargin set
                $ay = $v['y'] - $this->tbrot_y0;
                if ($this->table_rotate > 1) { // clockwise
                    $bx = $this->tbrot_x0 + $xadj - $ay;
                    $by = $this->tbrot_y0 + $yadj + $ax;
                } else {
                    $bx = $this->tbrot_x0 + $xadj + $ay;
                    $by = $this->tbrot_y0 + $yadj - $ax;
                }
                if ($v['x'] < 0) {
                    $v['x'] = -$bx;
                } else {
                    $v['x'] = $bx;
                }
                $v['y'] = ($by);
                $this->PageAnnots[$p][] = $v;
            }
        }
        $this->tbrot_Annots = [];
        /* -- END ANNOTATIONS -- */


        /* -- BOOKMARKS -- */

        // Adjust Bookmarks
        foreach ($this->tbrot_BMoutlines as $v) {
            $v['y'] = $this->tbrot_y0;
            $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $v['y'], 'p' => $this->page];
        }
        /* -- END BOOKMARKS -- */

        /* -- TOC -- */

        // Adjust ToC - uses document page number
        foreach ($this->tbrot_toc as $v) {
            $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $this->page, 'link' => $v['link'], 'toc_id' => $v['toc_id']];
            $this->links[$v['link']][1] = $this->tbrot_y0;
        }
        /* -- END TOC -- */



        $this->tbrot_BMoutlines = [];
        $this->tbrot_toc = [];

        // Stop Transformation
        $this->pages[$this->page] .= $this->StopTransform(true) . "\n";


        $this->y = $this->tbrot_y0 + $this->tbrot_w;
        $this->x = $this->lMargin;

        $this->tablebuffer = '';
    }

    // ==================================================================
    // Keep-with-table This buffers contents of h1-6 to keep on page with table
    public function printkwtbuffer()
    {
        if (!$this->kwt_moved) {
            foreach ($this->kwt_buffer as $s) {
                $this->pages[$this->page] .= $s['s'] . "\n";
            }
            foreach ($this->kwt_Links as $p => $l) {
                foreach ($l as $v) {
                    $this->PageLinks[$p][] = $v;
                }
            }
            $this->kwt_Links = [];
            /* -- ANNOTATIONS -- */
            foreach ($this->kwt_Annots as $p => $l) {
                foreach ($l as $v) {
                    $this->PageAnnots[$p][] = $v;
                }
            }
            $this->kwt_Annots = [];
            /* -- END ANNOTATIONS -- */

            /* -- INDEX -- */
            // Output Reference (index)
            foreach ($this->kwt_Reference as $v) {
                $Present = 0;
                for ($i = 0; $i < count($this->Reference); $i++) {
                    if ($this->Reference[$i]['t'] == $v['t']) {
                        $Present = 1;
                        if (!in_array($v['op'], $this->Reference[$i]['p'])) {
                            $this->Reference[$i]['p'][] = $v['op'];
                        }
                    }
                }
                if ($Present == 0) {
                    $this->Reference[] = ['t' => $v['t'], 'p' => [$v['op']]];
                }
            }
            $this->kwt_Reference = [];
            /* -- END INDEX -- */

            /* -- BOOKMARKS -- */
            // Output Bookmarks
            foreach ($this->kwt_BMoutlines as $v) {
                $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $v['y'], 'p' => $v['p']];
            }
            $this->kwt_BMoutlines = [];
            /* -- END BOOKMARKS -- */

            /* -- TOC -- */
            // Output ToC
            foreach ($this->kwt_toc as $v) {
                $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $v['p'], 'link' => $v['link'], 'toc_id' => $v['toc_id']];
            }
            $this->kwt_toc = [];
            /* -- END TOC -- */

            $this->pageoutput[$this->page] = []; // mPDF 6
            return;
        }

        // Start Transformation
        $this->pages[$this->page] .= $this->StartTransform(true) . "\n";
        $xadj = $this->lMargin - $this->kwt_x0;
        // $yadj = $this->y - $this->kwt_y0 ;
        $yadj = $this->tMargin - $this->kwt_y0;

        $this->pages[$this->page] .= $this->transformTranslate($xadj, $yadj, true) . "\n";

        // Now output the adjusted values
        foreach ($this->kwt_buffer as $s) {
            $this->pages[$this->page] .= $s['s'] . "\n";
        }

        // Adjust hyperLinks
        foreach ($this->kwt_Links as $p => $l) {
            foreach ($l as $v) {
                $bx = $this->kwt_x0 + $xadj;
                $by = $this->kwt_y0 + $yadj;
                $v[0] = $bx * Mpdf::SCALE;
                $v[1] = ($this->h - $by) * Mpdf::SCALE;
                $this->PageLinks[$p][] = $v;
            }
        }
        foreach ($this->internallink as $key => $f) {
            if (is_array($f) && isset($f['kwt'])) {
                $f['Y'] += $yadj;
                $f['PAGE'] = $this->page;
                unset($f['kwt']);
                $this->internallink[$key] = $f;
            }
        }
        /* -- ANNOTATIONS -- */
        foreach ($this->kwt_Annots as $p => $l) {
            foreach ($l as $v) {
                $bx = $this->kwt_x0 + $xadj;
                $by = $this->kwt_y0 + $yadj;
                if ($v['x'] < 0) {
                    $v['x'] = -$bx;
                } else {
                    $v['x'] = $bx;
                }
                $v['y'] = $by;
                $this->PageAnnots[$p][] = $v;
            }
        }
        /* -- END ANNOTATIONS -- */

        /* -- BOOKMARKS -- */

        // Adjust Bookmarks
        foreach ($this->kwt_BMoutlines as $v) {
            if ($v['y'] != 0) {
                $v['y'] += $yadj;
            }
            $this->BMoutlines[] = ['t' => $v['t'], 'l' => $v['l'], 'y' => $v['y'], 'p' => $this->page];
        }
        /* -- END BOOKMARKS -- */

        /* -- INDEX -- */

        // Adjust Reference (index)
        foreach ($this->kwt_Reference as $v) {
            $Present = 0;
            // Search the reference (AND Ref/PageNo) in the array
            for ($i = 0; $i < count($this->Reference); $i++) {
                if ($this->Reference[$i]['t'] == $v['t']) {
                    $Present = 1;
                    if (!in_array($this->page, $this->Reference[$i]['p'])) {
                        $this->Reference[$i]['p'][] = $this->page;
                    }
                }
            }
            if ($Present == 0) {
                $this->Reference[] = ['t' => $v['t'], 'p' => [$this->page]];
            }
        }
        /* -- END INDEX -- */

        /* -- TOC -- */

        // Adjust ToC
        foreach ($this->kwt_toc as $v) {
            $this->tableOfContents->_toc[] = ['t' => $v['t'], 'l' => $v['l'], 'p' => $this->page, 'link' => $v['link'], 'toc_id' => $v['toc_id']];
            $this->links[$v['link']][0] = $this->page;
            $this->links[$v['link']][1] += $yadj;
        }
        /* -- END TOC -- */


        $this->kwt_Links = [];
        $this->kwt_Annots = [];

        $this->kwt_Reference = [];
        $this->kwt_BMoutlines = [];
        $this->kwt_toc = [];
        // Stop Transformation
        $this->pages[$this->page] .= $this->StopTransform(true) . "\n";

        $this->kwt_buffer = [];

        $this->y += $this->kwt_height;
        $this->pageoutput[$this->page] = []; // mPDF 6
    }

    /* -- END TABLES -- */

    // ==================================================================

    public function printfloatbuffer()
    {
        if (count($this->floatbuffer)) {
            $this->objectbuffer = $this->floatbuffer;
            $this->printobjectbuffer(false);
            $this->objectbuffer = [];
            $this->floatbuffer = [];
            $this->floatmargins = [];
        }
    }

    // ==================================================================
    // ==================================================================
    // Added ELLIPSES and CIRCLES
    public function Circle($x, $y, $r, $style = 'S')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    public function Ellipse($x, $y, $rx, $ry, $style = 'S')
    {
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' or $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $h = $this->h;
        $this->_out(sprintf('%.3F %.3F m %.3F %.3F %.3F %.3F %.3F %.3F c', ($x + $rx) * Mpdf::SCALE, ($h - $y) * Mpdf::SCALE, ($x + $rx) * Mpdf::SCALE, ($h - ($y - $ly)) * Mpdf::SCALE, ($x + $lx) * Mpdf::SCALE, ($h - ($y - $ry)) * Mpdf::SCALE, $x * Mpdf::SCALE, ($h - ($y - $ry)) * Mpdf::SCALE));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', ($x - $lx) * Mpdf::SCALE, ($h - ($y - $ry)) * Mpdf::SCALE, ($x - $rx) * Mpdf::SCALE, ($h - ($y - $ly)) * Mpdf::SCALE, ($x - $rx) * Mpdf::SCALE, ($h - $y) * Mpdf::SCALE));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', ($x - $rx) * Mpdf::SCALE, ($h - ($y + $ly)) * Mpdf::SCALE, ($x - $lx) * Mpdf::SCALE, ($h - ($y + $ry)) * Mpdf::SCALE, $x * Mpdf::SCALE, ($h - ($y + $ry)) * Mpdf::SCALE));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c %s', ($x + $lx) * Mpdf::SCALE, ($h - ($y + $ry)) * Mpdf::SCALE, ($x + $rx) * Mpdf::SCALE, ($h - ($y + $ly)) * Mpdf::SCALE, ($x + $rx) * Mpdf::SCALE, ($h - $y) * Mpdf::SCALE, $op));
    }

    /* -- DIRECTW -- */

    // Added adaptation of shaded_box = AUTOSIZE-TEXT
    public function AutosizeText($text, $w, $font, $style, $szfont = 72)
    {

        $text = ' ' . $text . ' ';

        $this->SetFont($font, $style, $szfont, false);

        $text = $this->purify_utf8_text($text);
        if ($this->text_input_as_HTML) {
            $text = $this->all_entities_to_utf8($text);
        }
        if ($this->usingCoreFont) {
            $text = mb_convert_encoding($text, $this->mb_enc, 'UTF-8');
        }

        // DIRECTIONALITY
        if (preg_match("/([" . $this->pregRTLchars . "])/u", $text)) {
            $this->biDirectional = true;
        } // *OTL*

        $textvar = 0;
        $save_OTLtags = $this->OTLtags;
        $this->OTLtags = [];
        if ($this->useKerning) {
            if ($this->CurrentFont['haskernGPOS']) {
                $this->OTLtags['Plus'] .= ' kern';
            } else {
                $textvar = ($textvar | TextVars::FC_KERNING);
            }
        }

        /* -- OTL -- */
        // Use OTL OpenType Table Layout - GSUB & GPOS
        if (isset($this->CurrentFont['useOTL']) && $this->CurrentFont['useOTL']) {
            $text = $this->otl->applyOTL($text, $this->CurrentFont['useOTL']);
            $OTLdata = $this->otl->OTLdata;
        }
        /* -- END OTL -- */
        $this->OTLtags = $save_OTLtags;

        $this->magic_reverse_dir($text, $this->directionality, $OTLdata);


        $width = $this->sizeConverter->convert($w);
        $loop = 0;
        while ($loop == 0) {
            $this->SetFont($font, $style, $szfont, false);
            $sz = $this->GetStringWidth($text, true, $OTLdata, $textvar);
            if ($sz > $w) {
                $szfont --;
            } else {
                $loop ++;
            }
        }
        $this->SetFont($font, $style, $szfont, true, true);
        $this->Cell($w, 0, $text, 0, 0, "C", 0, '', 0, 0, 0, 'M', 0, false, $OTLdata, $textvar);
    }

    /* -- END DIRECTW -- */

    // ====================================================
    // ====================================================

    public function magic_reverse_dir(&$chunk, $dir, &$chunkOTLdata)
    {
        /* -- OTL -- */
        if ($this->usingCoreFont) {
            return 0;
        }
        if ($chunk == '') {
            return 0;
        }

        if ($this->biDirectional || $dir == 'rtl') {
            // check if string contains RTL text
            // including any added from OTL tables (in PUA)
            $pregRTLchars = $this->pregRTLchars;
            if (isset($this->CurrentFont['rtlPUAstr']) && $this->CurrentFont['rtlPUAstr']) {
                $pregRTLchars .= $this->CurrentFont['rtlPUAstr'];
            }
            if (!preg_match("/[" . $pregRTLchars . "]/u", $chunk) && $dir != 'rtl') {
                return 0;
            }   // Chunk doesn't contain RTL characters

            $unicode = $this->UTF8StringToArray($chunk, false);

            $is_strong = false;
            if (empty($chunkOTLdata)) {
                $this->getBasicOTLdata($chunkOTLdata, $unicode, $is_strong);
            }

            if (isset($this->CurrentFont['useOTL']) && ($this->CurrentFont['useOTL'] & 0x80)) {
                $useGPOS = true;
            } else {
                $useGPOS = false;
            }

            // NB Returned $chunk may be a shorter string (with adjusted $cOTLdata) by removal of LRE, RLE etc embedding codes.
            list($chunk, $rtl_content) = $this->otl->bidiSort($unicode, $chunk, $dir, $chunkOTLdata, $useGPOS);

            return $rtl_content;
        }
        /* -- END OTL -- */
        return 0;
    }

    /* -- OTL -- */

    public function getBasicOTLdata(&$chunkOTLdata, $unicode, &$is_strong)
    {
        if (empty($this->otl)) {
            $this->otl = new Otl($this, $this->fontCache);
        }
        $chunkOTLdata['group'] = '';
        $chunkOTLdata['GPOSinfo'] = [];
        $chunkOTLdata['char_data'] = [];
        foreach ($unicode as $char) {
            $ucd_record = Ucdn::get_ucd_record($char);
            $chunkOTLdata['char_data'][] = ['bidi_class' => $ucd_record[2], 'uni' => $char];
            if ($ucd_record[2] == 0 || $ucd_record[2] == 3 || $ucd_record[2] == 4) {
                $is_strong = true;
            } // contains strong character
            if ($ucd_record[0] == Ucdn::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK) {
                $chunkOTLdata['group'] .= 'M';
            } elseif ($char == 32 || $char == 12288) {
                $chunkOTLdata['group'] .= 'S';
            } else {
                $chunkOTLdata['group'] .= 'C';
            }
        }
    }

    /* -- END OTL -- */

    //
    // ****************************
    // ****************************


    public function SetSubstitutions()
    {
        $subsarray = [];
        require __DIR__ . '/../data/subs_win-1252.php';
        $this->substitute = [];
        foreach ($subsarray as $key => $val) {
            $this->substitute[UtfString::code2utf($key)] = $val;
        }
    }

    public function SubstituteChars($html)
    {
        // only substitute characters between tags
        if (count($this->substitute)) {
            $a = preg_split('/(<.*?>)/ms', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            $html = '';
            foreach ($a as $i => $e) {
                if ($i % 2 == 0) {
                    $e = strtr($e, $this->substitute);
                }
                $html .= $e;
            }
        }
        return $html;
    }

    public function SubstituteCharsSIP(&$writehtml_a, &$writehtml_i, &$writehtml_e)
    {
        if (preg_match("/^(.*?)([\x{20000}-\x{2FFFF}]+)(.*)/u", $writehtml_e, $m)) {
            if (isset($this->CurrentFont['sipext']) && $this->CurrentFont['sipext']) {
                $font = $this->CurrentFont['sipext'];
                if (!in_array($font, $this->available_unifonts)) {
                    return 0;
                }
                $writehtml_a[$writehtml_i] = $writehtml_e = $m[1];
                array_splice($writehtml_a, $writehtml_i + 1, 0, ['span style="font-family: ' . $font . '"', $m[2], '/span', $m[3]]);
                $this->subPos = $writehtml_i;
                return 4;
            }
        }
        return 0;
    }

    // If core font is selected in document which is not onlyCoreFonts - substitute with non-core font
    public function SubstituteCharsNonCore(&$writehtml_a, &$writehtml_i, &$writehtml_e)
    {
        // Ignore if in Textarea
        if ($writehtml_i > 0 && strtolower(substr($writehtml_a[$writehtml_i - 1], 0, 8)) == 'textarea') {
            return 0;
        }
        if (mb_convert_encoding(mb_convert_encoding($writehtml_e, $this->mb_enc, "UTF-8"), "UTF-8", $this->mb_enc) == $writehtml_e) {
            return 0;
        }
        $cw = &$this->CurrentFont['cw'];
        $unicode = $this->UTF8StringToArray($writehtml_e, false);
        $start = -1;
        $end = 0;
        $flag = 0;
        $ftype = '';
        $u = [];
        if (!$this->subArrMB) {
            require __DIR__ . '/../data/subs_core.php';
            $this->subArrMB['a'] = $aarr;
            $this->subArrMB['s'] = $sarr;
            $this->subArrMB['z'] = $zarr;
        }
        foreach ($unicode as $c => $char) {
            if (($char > 127 || ($flag == 1 && $char == 32)) && $char != 173 && (!isset($this->subArrMB['a'][$char]) || ($flag == 1 && $char == 32)) && ($char < 1536 || ($char > 1791 && $char < 2304) || $char > 3455)) {
                if ($flag == 0) {
                    $start = $c;
                }
                $flag = 1;
                $u[] = $char;
            } elseif ($flag > 0) {
                $end = $c - 1;
                break;
            }
        }
        if ($flag > 0 && !$end) {
            $end = count($unicode) - 1;
        }
        if ($start == -1) {
            return 0;
        }

        // TRY IN BACKUP SUBS FONT
        if (!is_array($this->backupSubsFont)) {
            $this->backupSubsFont = ["$this->backupSubsFont"];
        }

        foreach ($this->backupSubsFont as $bsfctr => $bsf) {
            if ($this->fonttrans[$bsf] == 'chelvetica' || $this->fonttrans[$bsf] == 'ctimes' || $this->fonttrans[$bsf] == 'ccourier') {
                continue;
            }

            $font = $bsf;
            unset($cw);
            $cw = '';

            if (isset($this->fonts[$font])) {
                $cw = &$this->fonts[$font]['cw'];
            } elseif ($this->fontCache->has($font . '.cw.dat')) {
                $cw = $this->fontCache->load($font . '.cw.dat');
            } else {
                $prevFontFamily = $this->FontFamily;
                $prevFontStyle = $this->currentfontstyle;
                $prevFontSizePt = $this->FontSizePt;
                $this->SetFont($bsf, '', '', false);
                $this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt, false);
            }

            if (!$cw) {
                continue;
            }

            $l = 0;
            foreach ($u as $char) {
                if ($char == 173 || $this->_charDefined($cw, $char) || ($char > 1536 && $char < 1791) || ($char > 2304 && $char < 3455 )) {
                    $l++;
                } else {
                    if ($l == 0 && $bsfctr == (count($this->backupSubsFont) - 1)) { // Not found even in last backup font
                        $cont = mb_substr($writehtml_e, $start + 1);
                        $writehtml_e = mb_substr($writehtml_e, 0, $start + 1, 'UTF-8');
                        array_splice($writehtml_a, $writehtml_i + 1, 0, ['', $cont]);
                        $this->subPos = $writehtml_i + 1;
                        return 2;
                    } else {
                        break;
                    }
                }
            }

            if ($l > 0) {
                $patt = mb_substr($writehtml_e, $start, $l, 'UTF-8');
                if (preg_match("/(.*?)(" . preg_quote($patt, '/') . ")(.*)/u", $writehtml_e, $m)) {
                    $writehtml_e = $m[1];
                    array_splice($writehtml_a, $writehtml_i + 1, 0, ['span style="font-family: ' . $font . '"', $m[2], '/span', $m[3]]);
                    $this->subPos = $writehtml_i + 3;
                    return 4;
                }
            }
        }

        unset($cw);
        return 0;
    }

    public function SubstituteCharsMB(&$writehtml_a, &$writehtml_i, &$writehtml_e)
    {
        // Ignore if in Textarea
        if ($writehtml_i > 0 && strtolower(substr($writehtml_a[$writehtml_i - 1], 0, 8)) == 'textarea') {
            return 0;
        }
        $cw = &$this->CurrentFont['cw'];
        $unicode = $this->UTF8StringToArray($writehtml_e, false);
        $start = -1;
        $end = 0;
        $flag = 0;
        $ftype = '';
        $u = [];
        foreach ($unicode as $c => $char) {
            if (($flag == 0 || $flag == 2) && (!$this->_charDefined($cw, $char) || ($flag == 2 && $char == 32)) && $this->checkSIP && $char > 131071) {  // Unicode Plane 2 (SIP)
                if (in_array($this->FontFamily, $this->available_CJK_fonts)) {
                    return 0;
                }
                if ($flag == 0) {
                    $start = $c;
                }
                $flag = 2;
                $u[] = $char;
            } // elseif (($flag == 0 || $flag==1) && $char != 173 && !$this->_charDefined($cw,$char) && ($char<1423 ||  ($char>3583 && $char < 11263))) {
            elseif (($flag == 0 || $flag == 1) && $char != 173 && (!$this->_charDefined($cw, $char) || ($flag == 1 && $char == 32)) && ($char < 1536 || ($char > 1791 && $char < 2304) || $char > 3455)) {
                if ($flag == 0) {
                    $start = $c;
                }
                $flag = 1;
                $u[] = $char;
            } elseif ($flag > 0) {
                $end = $c - 1;
                break;
            }
        }
        if ($flag > 0 && !$end) {
            $end = count($unicode) - 1;
        }
        if ($start == -1) {
            return 0;
        }

        if ($flag == 2) {  // SIP
            // Check if current CJK font has a ext-B related font
            if (isset($this->CurrentFont['sipext']) && $this->CurrentFont['sipext']) {
                $font = $this->CurrentFont['sipext'];
                unset($cw);
                $cw = '';
                if (isset($this->fonts[$font])) {
                    $cw = &$this->fonts[$font]['cw'];
                } elseif ($this->fontCache->has($font . '.cw.dat')) {
                    $cw = $this->fontCache->load($font . '.cw.dat');
                } else {
                    $prevFontFamily = $this->FontFamily;
                    $prevFontStyle = $this->currentfontstyle;
                    $prevFontSizePt = $this->FontSizePt;
                    $this->SetFont($font, '', '', false);
                    $this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt, false);
                }

                if (!$cw) {
                    return 0;
                }

                $l = 0;
                foreach ($u as $char) {
                    if ($this->_charDefined($cw, $char) || $char > 131071) {
                        $l++;
                    } else {
                        break;
                    }
                }

                if ($l > 0) {
                    $patt = mb_substr($writehtml_e, $start, $l);
                    if (preg_match("/(.*?)(" . preg_quote($patt, '/') . ")(.*)/u", $writehtml_e, $m)) {
                        $writehtml_e = $m[1];
                        array_splice($writehtml_a, $writehtml_i + 1, 0, ['span style="font-family: ' . $font . '"', $m[2], '/span', $m[3]]);
                        $this->subPos = $writehtml_i + 3;
                        return 4;
                    }
                }
            }
            // Check Backup SIP font (defined in Config\FontVariables)
            if (isset($this->backupSIPFont) && $this->backupSIPFont) {
                if ($this->currentfontfamily != $this->backupSIPFont) {
                    $font = $this->backupSIPFont;
                } else {
                    unset($cw);
                    return 0;
                }

                unset($cw);
                $cw = '';

                if (isset($this->fonts[$font])) {
                    $cw = &$this->fonts[$font]['cw'];
                } elseif ($this->fontCache->has($font . '.cw.dat')) {
                    $cw = $this->fontCache->load($font . '.cw.dat');
                } else {
                    $prevFontFamily = $this->FontFamily;
                    $prevFontStyle = $this->currentfontstyle;
                    $prevFontSizePt = $this->FontSizePt;
                    $this->SetFont($this->backupSIPFont, '', '', false);
                    $this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt, false);
                }

                if (!$cw) {
                    return 0;
                }

                $l = 0;
                foreach ($u as $char) {
                    if ($this->_charDefined($cw, $char) || $char > 131071) {
                        $l++;
                    } else {
                        break;
                    }
                }
                if ($l > 0) {
                    $patt = mb_substr($writehtml_e, $start, $l);
                    if (preg_match("/(.*?)(" . preg_quote($patt, '/') . ")(.*)/u", $writehtml_e, $m)) {
                        $writehtml_e = $m[1];
                        array_splice($writehtml_a, $writehtml_i + 1, 0, ['span style="font-family: ' . $font . '"', $m[2], '/span', $m[3]]);
                        $this->subPos = $writehtml_i + 3;
                        return 4;
                    }
                }
            }
            return 0;
        }


        // FIRST TRY CORE FONTS (when appropriate)
        if (!$this->PDFA && !$this->PDFX && !$this->biDirectional) {  // mPDF 6
            $repl = [];
            if (!$this->subArrMB) {
                require __DIR__ . '/../data/subs_core.php';
                $this->subArrMB['a'] = $aarr;
                $this->subArrMB['s'] = $sarr;
                $this->subArrMB['z'] = $zarr;
            }
            if (isset($this->subArrMB['a'][$u[0]])) {
                $font = 'tta';
                $ftype = 'C';
                foreach ($u as $char) {
                    if (isset($this->subArrMB['a'][$char])) {
                        $repl[] = $this->subArrMB['a'][$char];
                    } else {
                        break;
                    }
                }
            } elseif (isset($this->subArrMB['z'][$u[0]])) {
                $font = 'ttz';
                $ftype = 'C';
                foreach ($u as $char) {
                    if (isset($this->subArrMB['z'][$char])) {
                        $repl[] = $this->subArrMB['z'][$char];
                    } else {
                        break;
                    }
                }
            } elseif (isset($this->subArrMB['s'][$u[0]])) {
                $font = 'tts';
                $ftype = 'C';
                foreach ($u as $char) {
                    if (isset($this->subArrMB['s'][$char])) {
                        $repl[] = $this->subArrMB['s'][$char];
                    } else {
                        break;
                    }
                }
            }
            if ($ftype == 'C') {
                $patt = mb_substr($writehtml_e, $start, count($repl));
                if (preg_match("/(.*?)(" . preg_quote($patt, '/') . ")(.*)/u", $writehtml_e, $m)) {
                    $writehtml_e = $m[1];
                    array_splice($writehtml_a, $writehtml_i + 1, 0, [$font, implode('|', $repl), '/' . $font, $m[3]]); // e.g. <tts>
                    $this->subPos = $writehtml_i + 3;
                    return 4;
                }
                return 0;
            }
        }

        // LASTLY TRY IN BACKUP SUBS FONT
        if (!is_array($this->backupSubsFont)) {
            $this->backupSubsFont = ["$this->backupSubsFont"];
        }
        foreach ($this->backupSubsFont as $bsfctr => $bsf) {
            if ($this->currentfontfamily != $bsf) {
                $font = $bsf;
            } else {
                continue;
            }

            unset($cw);
            $cw = '';

            if (isset($this->fonts[$font])) {
                $cw = &$this->fonts[$font]['cw'];
            } elseif ($this->fontCache->has($font . '.cw.dat')) {
                $cw = $this->fontCache->load($font . '.cw.dat');
            } else {
                $prevFontFamily = $this->FontFamily;
                $prevFontStyle = $this->currentfontstyle;
                $prevFontSizePt = $this->FontSizePt;
                $this->SetFont($bsf, '', '', false);
                $this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt, false);
            }

            if (!$cw) {
                continue;
            }

            $l = 0;
            foreach ($u as $char) {
                if ($char == 173 || $this->_charDefined($cw, $char) || ($char > 1536 && $char < 1791) || ($char > 2304 && $char < 3455 )) {  // Arabic and Indic
                    $l++;
                } else {
                    if ($l == 0 && $bsfctr == (count($this->backupSubsFont) - 1)) { // Not found even in last backup font
                        $cont = mb_substr($writehtml_e, $start + 1);
                        $writehtml_e = mb_substr($writehtml_e, 0, $start + 1);
                        array_splice($writehtml_a, $writehtml_i + 1, 0, ['', $cont]);
                        $this->subPos = $writehtml_i + 1;
                        return 2;
                    } else {
                        break;
                    }
                }
            }
            if ($l > 0) {
                $patt = mb_substr($writehtml_e, $start, $l);
                if (preg_match("/(.*?)(" . preg_quote($patt, '/') . ")(.*)/u", $writehtml_e, $m)) {
                    $writehtml_e = $m[1];
                    array_splice($writehtml_a, $writehtml_i + 1, 0, ['span style="font-family: ' . $font . '"', $m[2], '/span', $m[3]]);
                    $this->subPos = $writehtml_i + 3;
                    return 4;
                }
            }
        }

        unset($cw);
        return 0;
    }

    public function setHiEntitySubstitutions()
    {
        $entarr = [
            'nbsp' => '160', 'iexcl' => '161', 'cent' => '162', 'pound' => '163', 'curren' => '164', 'yen' => '165', 'brvbar' => '166', 'sect' => '167',
            'uml' => '168', 'copy' => '169', 'ordf' => '170', 'laquo' => '171', 'not' => '172', 'shy' => '173', 'reg' => '174', 'macr' => '175',
            'deg' => '176', 'plusmn' => '177', 'sup2' => '178', 'sup3' => '179', 'acute' => '180', 'micro' => '181', 'para' => '182', 'middot' => '183',
            'cedil' => '184', 'sup1' => '185', 'ordm' => '186', 'raquo' => '187', 'frac14' => '188', 'frac12' => '189', 'frac34' => '190',
            'iquest' => '191', 'Agrave' => '192', 'Aacute' => '193', 'Acirc' => '194', 'Atilde' => '195', 'Auml' => '196', 'Aring' => '197',
            'AElig' => '198', 'Ccedil' => '199', 'Egrave' => '200', 'Eacute' => '201', 'Ecirc' => '202', 'Euml' => '203', 'Igrave' => '204',
            'Iacute' => '205', 'Icirc' => '206', 'Iuml' => '207', 'ETH' => '208', 'Ntilde' => '209', 'Ograve' => '210', 'Oacute' => '211',
            'Ocirc' => '212', 'Otilde' => '213', 'Ouml' => '214', 'times' => '215', 'Oslash' => '216', 'Ugrave' => '217', 'Uacute' => '218',
            'Ucirc' => '219', 'Uuml' => '220', 'Yacute' => '221', 'THORN' => '222', 'szlig' => '223', 'agrave' => '224', 'aacute' => '225',
            'acirc' => '226', 'atilde' => '227', 'auml' => '228', 'aring' => '229', 'aelig' => '230', 'ccedil' => '231', 'egrave' => '232',
            'eacute' => '233', 'ecirc' => '234', 'euml' => '235', 'igrave' => '236', 'iacute' => '237', 'icirc' => '238', 'iuml' => '239',
            'eth' => '240', 'ntilde' => '241', 'ograve' => '242', 'oacute' => '243', 'ocirc' => '244', 'otilde' => '245', 'ouml' => '246',
            'divide' => '247', 'oslash' => '248', 'ugrave' => '249', 'uacute' => '250', 'ucirc' => '251', 'uuml' => '252', 'yacute' => '253',
            'thorn' => '254', 'yuml' => '255', 'OElig' => '338', 'oelig' => '339', 'Scaron' => '352', 'scaron' => '353', 'Yuml' => '376',
            'fnof' => '402', 'circ' => '710', 'tilde' => '732', 'Alpha' => '913', 'Beta' => '914', 'Gamma' => '915', 'Delta' => '916',
            'Epsilon' => '917', 'Zeta' => '918', 'Eta' => '919', 'Theta' => '920', 'Iota' => '921', 'Kappa' => '922', 'Lambda' => '923',
            'Mu' => '924', 'Nu' => '925', 'Xi' => '926', 'Omicron' => '927', 'Pi' => '928', 'Rho' => '929', 'Sigma' => '931', 'Tau' => '932',
            'Upsilon' => '933', 'Phi' => '934', 'Chi' => '935', 'Psi' => '936', 'Omega' => '937', 'alpha' => '945', 'beta' => '946', 'gamma' => '947',
            'delta' => '948', 'epsilon' => '949', 'zeta' => '950', 'eta' => '951', 'theta' => '952', 'iota' => '953', 'kappa' => '954',
            'lambda' => '955', 'mu' => '956', 'nu' => '957', 'xi' => '958', 'omicron' => '959', 'pi' => '960', 'rho' => '961', 'sigmaf' => '962',
            'sigma' => '963', 'tau' => '964', 'upsilon' => '965', 'phi' => '966', 'chi' => '967', 'psi' => '968', 'omega' => '969',
            'thetasym' => '977', 'upsih' => '978', 'piv' => '982', 'ensp' => '8194', 'emsp' => '8195', 'thinsp' => '8201', 'zwnj' => '8204',
            'zwj' => '8205', 'lrm' => '8206', 'rlm' => '8207', 'ndash' => '8211', 'mdash' => '8212', 'lsquo' => '8216', 'rsquo' => '8217',
            'sbquo' => '8218', 'ldquo' => '8220', 'rdquo' => '8221', 'bdquo' => '8222', 'dagger' => '8224', 'Dagger' => '8225', 'bull' => '8226',
            'hellip' => '8230', 'permil' => '8240', 'prime' => '8242', 'Prime' => '8243', 'lsaquo' => '8249', 'rsaquo' => '8250', 'oline' => '8254',
            'frasl' => '8260', 'euro' => '8364', 'image' => '8465', 'weierp' => '8472', 'real' => '8476', 'trade' => '8482', 'alefsym' => '8501',
            'larr' => '8592', 'uarr' => '8593', 'rarr' => '8594', 'darr' => '8595', 'harr' => '8596', 'crarr' => '8629', 'lArr' => '8656',
            'uArr' => '8657', 'rArr' => '8658', 'dArr' => '8659', 'hArr' => '8660', 'forall' => '8704', 'part' => '8706', 'exist' => '8707',
            'empty' => '8709', 'nabla' => '8711', 'isin' => '8712', 'notin' => '8713', 'ni' => '8715', 'prod' => '8719', 'sum' => '8721',
            'minus' => '8722', 'lowast' => '8727', 'radic' => '8730', 'prop' => '8733', 'infin' => '8734', 'ang' => '8736', 'and' => '8743',
            'or' => '8744', 'cap' => '8745', 'cup' => '8746', 'int' => '8747', 'there4' => '8756', 'sim' => '8764', 'cong' => '8773',
            'asymp' => '8776', 'ne' => '8800', 'equiv' => '8801', 'le' => '8804', 'ge' => '8805', 'sub' => '8834', 'sup' => '8835', 'nsub' => '8836',
            'sube' => '8838', 'supe' => '8839', 'oplus' => '8853', 'otimes' => '8855', 'perp' => '8869', 'sdot' => '8901', 'lceil' => '8968',
            'rceil' => '8969', 'lfloor' => '8970', 'rfloor' => '8971', 'lang' => '9001', 'rang' => '9002', 'loz' => '9674', 'spades' => '9824',
            'clubs' => '9827', 'hearts' => '9829', 'diams' => '9830',
        ];
        foreach ($entarr as $key => $val) {
            $this->entsearch[] = '&' . $key . ';';
            $this->entsubstitute[] = UtfString::code2utf($val);
        }
    }

    public function SubstituteHiEntities($html)
    {
        // converts html_entities > ASCII 127 to unicode
        // Leaves in particular &lt; to distinguish from tag marker
        if (count($this->entsearch)) {
            $html = str_replace($this->entsearch, $this->entsubstitute, $html);
        }
        return $html;
    }

    // Edited v1.2 Pass by reference; option to continue if invalid UTF-8 chars
    public function is_utf8(&$string)
    {
        if ($string === mb_convert_encoding(mb_convert_encoding($string, "UTF-32", "UTF-8"), "UTF-8", "UTF-32")) {
            return true;
        } else {
            if ($this->ignore_invalid_utf8) {
                $string = mb_convert_encoding(mb_convert_encoding($string, "UTF-32", "UTF-8"), "UTF-8", "UTF-32");
                return true;
            } else {
                return false;
            }
        }
    }

    public function purify_utf8($html, $lo = true)
    {
        // For HTML
        // Checks string is valid UTF-8 encoded
        // converts html_entities > ASCII 127 to UTF-8
        // Only exception - leaves low ASCII entities e.g. &lt; &amp; etc.
        // Leaves in particular &lt; to distinguish from tag marker
        if (!$this->is_utf8($html)) {
            while (mb_convert_encoding(mb_convert_encoding($html, "UTF-32", "UTF-8"), "UTF-8", "UTF-32") != $html) {
                $a = iconv('UTF-8', 'UTF-8', $html);
                // echo ($a);
                $pos = $start = strlen($a);
                $err = '';
                while (ord(substr($html, $pos, 1)) > 128) {
                    $err .= '[[#' . ord(substr($html, $pos, 1)) . ']]';
                    $pos++;
                }
                $this->logger->error($err, ['context' => LogContext::UTF8]);
                $html = substr($html, $pos);
            }
            throw new \Mpdf\MpdfException("HTML contains invalid UTF-8 character(s). See log for further details");
        }
        $html = preg_replace("/\r/", "", $html);

        // converts html_entities > ASCII 127 to UTF-8
        // Leaves in particular &lt; to distinguish from tag marker
        $html = $this->SubstituteHiEntities($html);

        // converts all &#nnn; or &#xHHH; to UTF-8 multibyte
        // If $lo==true then includes ASCII < 128
        $html = UtfString::strcode2utf($html, $lo);
        return ($html);
    }

    public function purify_utf8_text($txt)
    {
        // For TEXT
        // Make sure UTF-8 string of characters
        if (!$this->is_utf8($txt)) {
            throw new \Mpdf\MpdfException("Text contains invalid UTF-8 character(s)");
        }

        $txt = preg_replace("/\r/", "", $txt);

        return ($txt);
    }

    public function all_entities_to_utf8($txt)
    {
        // converts txt_entities > ASCII 127 to UTF-8
        // Leaves in particular &lt; to distinguish from tag marker
        $txt = $this->SubstituteHiEntities($txt);

        // converts all &#nnn; or &#xHHH; to UTF-8 multibyte
        $txt = UtfString::strcode2utf($txt);

        $txt = $this->lesser_entity_decode($txt);
        return ($txt);
    }

    // ====================================================
        /* -- BARCODES -- */
    // UPC/EAN barcode
    // EAN13, EAN8, UPCA, UPCE, ISBN, ISSN
    // Accepts 12 or 13 digits with or without - hyphens
    public function WriteBarcode($code, $showtext = 1, $x = '', $y = '', $size = 1, $border = 0, $paddingL = 1, $paddingR = 1, $paddingT = 2, $paddingB = 2, $height = 1, $bgcol = false, $col = false, $btype = 'ISBN', $supplement = '0', $supplement_code = '', $k = 1)
    {
        if (empty($code)) {
            return;
        }
        $codestr = $code;
        $code = preg_replace('/\-/', '', $code);

        $this->barcode = new Barcode();
        if ($btype == 'ISSN' || $btype == 'ISBN') {
            $arrcode = $this->barcode->getBarcodeArray($code, 'EAN13');
        } else {
            $arrcode = $this->barcode->getBarcodeArray($code, $btype);
        }

        if ($arrcode === false) {
            throw new \Mpdf\MpdfException('Error in barcode string: ' . $codestr);
        }
        if ((($btype == 'EAN13' || $btype == 'ISBN' || $btype == 'ISSN') && strlen($code) == 12) || ($btype == 'UPCA' && strlen($code) == 11) || ($btype == 'UPCE' && strlen($code) == 11) || ($btype == 'EAN8' && strlen($code) == 7)) {
            $code .= $arrcode['checkdigit'];
            if (stristr($codestr, '-')) {
                $codestr .= '-' . $arrcode['checkdigit'];
            } else {
                $codestr .= $arrcode['checkdigit'];
            }
        }
        if ($btype == 'ISBN') {
            $codestr = 'ISBN ' . $codestr;
        }
        if ($btype == 'ISSN') {
            $codestr = 'ISSN ' . $codestr;
        }

        if (empty($x)) {
            $x = $this->x;
        }
        if (empty($y)) {
            $y = $this->y;
        }
        // set foreground color
        $prevDrawColor = $this->DrawColor;
        $prevTextColor = $this->TextColor;
        $prevFillColor = $this->FillColor;
        $lw = $this->LineWidth;
        $this->SetLineWidth(0.01);

        $size /= $k; // in case resized in a table

        $xres = $arrcode['nom-X'] * $size;
        $llm = $arrcode['lightmL'] * $arrcode['nom-X'] * $size; // Left Light margin
        $rlm = $arrcode['lightmR'] * $arrcode['nom-X'] * $size; // Right Light margin

        $bcw = ($arrcode["maxw"] * $xres); // Barcode width = Should always be 31.35mm * $size

        $fbw = $bcw + $llm + $rlm; // Full barcode width incl. light margins
        $ow = $fbw + $paddingL + $paddingR; // Full overall width incl. user-defined padding

        $fbwi = $fbw - 2; // Full barcode width incl. light margins - 2mm - for isbn string
        // cf. http://www.gs1uk.org/downloads/bar_code/Bar coding getting it right.pdf
        $num_height = 3 * $size;     // Height of numerals
        $fbh = $arrcode['nom-H'] * $size * $height;  // Full barcode height incl. numerals
        $bch = $fbh - (1.5 * $size);     // Barcode height of bars   (3mm for numerals)

        if (($btype == 'EAN13' && $showtext) || $btype == 'ISSN' || $btype == 'ISBN') { // Add height for ISBN string + margin from top of bars
            $tisbnm = 1.5 * $size; // Top margin between isbn (if shown) & bars
            $codestr_fontsize = 2.1 * $size;
            $paddingT += $codestr_fontsize + $tisbnm;
        }
        $oh = $fbh + $paddingT + $paddingB;  // Full overall height incl. user-defined padding
        // PRINT border background color
        $xpos = $x;
        $ypos = $y;
        if ($col) {
            $this->SetDColor($col);
            $this->SetTColor($col);
        } else {
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        }
        if ($bgcol) {
            $this->SetFColor($bgcol);
        } else {
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
        }
        if (!$bgcol && !$col) { // fn. called directly - not via HTML
            if ($border) {
                $fillb = 'DF';
            } else {
                $fillb = 'F';
            }
            $this->Rect($xpos, $ypos, $ow, $oh, $fillb);
        }


        // PRINT BARS
        $xpos = $x + $paddingL + $llm;
        $ypos = $y + $paddingT;
        if ($col) {
            $this->SetFColor($col);
        } else {
            $this->SetFColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        }
        if ($arrcode !== false) {
            foreach ($arrcode["bcode"] as $v) {
                $bw = ($v["w"] * $xres);
                if ($v["t"]) {
                    // draw a vertical bar
                    $this->Rect($xpos, $ypos, $bw, $bch, 'F');
                }
                $xpos += $bw;
            }
        }


        // print text
        $prevFontFamily = $this->FontFamily;
        $prevFontStyle = $this->FontStyle;
        $prevFontSizePt = $this->FontSizePt;

        // ISBN string
        if (($btype == 'EAN13' && $showtext) || $btype == 'ISBN' || $btype == 'ISSN') {
            if ($this->onlyCoreFonts) {
                $this->SetFont('chelvetica');
            } else {
                $this->SetFont('sans');
            }

            if ($bgcol) {
                $this->SetFColor($bgcol);
            } else {
                $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
            }
            $this->x = $x + $paddingL + 1; // 1mm left margin (cf. $fbwi above)
            // max width is $fbwi
            $loop = 0;
            while ($loop == 0) {
                $this->SetFontSize($codestr_fontsize * 1.4 * Mpdf::SCALE, false); // don't write
                $sz = $this->GetStringWidth($codestr);
                if ($sz > $fbwi) {
                    $codestr_fontsize -= 0.1;
                } else {
                    $loop ++;
                }
            }
            $this->SetFont('', '', $codestr_fontsize * 1.4 * Mpdf::SCALE, true, true); // * 1.4 because font height is only 7/10 of given mm
            // WORD SPACING
            if ($fbwi > $sz) {
                $xtra = $fbwi - $sz;
                $charspacing = $xtra / (strlen($codestr) - 1);
                if ($charspacing) {
                    $this->_out(sprintf('BT %.3F Tc ET', $charspacing * Mpdf::SCALE));
                }
            }
            $this->y = $y + $paddingT - ($codestr_fontsize ) - $tisbnm;
            $this->Cell($fbw, $codestr_fontsize, $codestr);
            if ($charspacing) {
                $this->_out('BT 0 Tc ET');
            }
        }


        // Bottom NUMERALS
        // mPDF 5.7.4
        if ($this->onlyCoreFonts) {
            $this->SetFont('ccourier');
            $fh = 1.3;
        } else {
            $this->SetFont('ocrb');
            $fh = 1.06;
        }
        $charRO = '';
        if ($btype == 'EAN13' || $btype == 'ISBN' || $btype == 'ISSN') {
            $outerfontsize = 3; // Inner fontsize = 3
            $outerp = $xres * 4;
            $innerp = $xres * 2.5;
            $textw = ($bcw * 0.5) - $outerp - $innerp;
            $chars = 6; // number of numerals in each half
            $charLO = substr($code, 0, 1); // Left Outer
            $charLI = substr($code, 1, 6); // Left Inner
            $charRI = substr($code, 7, 6); // Right Inner
            if (!$supplement) {
                $charRO = '>'; // Right Outer
            }
        } elseif ($btype == 'UPCA') {
            $outerfontsize = 2.3; // Inner fontsize = 3
            $outerp = $xres * 10;
            $innerp = $xres * 2.5;
            $textw = ($bcw * 0.5) - $outerp - $innerp;
            $chars = 5;
            $charLO = substr($code, 0, 1); // Left Outer
            $charLI = substr($code, 1, 5); // Left Inner
            $charRI = substr($code, 6, 5); // Right Inner
            $charRO = substr($code, 11, 1); // Right Outer
        } elseif ($btype == 'UPCE') {
            $outerfontsize = 2.3; // Inner fontsize = 3
            $outerp = $xres * 4;
            $innerp = 0;
            $textw = ($bcw * 0.5) - $outerp - $innerp;
            $chars = 3;
            $upce_code = $arrcode['code'];
            $charLO = substr($code, 0, 1); // Left Outer
            $charLI = substr($upce_code, 0, 3); // Left Inner
            $charRI = substr($upce_code, 3, 3); // Right Inner
            $charRO = substr($code, 11, 1); // Right Outer
        } elseif ($btype == 'EAN8') {
            $outerfontsize = 3; // Inner fontsize = 3
            $outerp = $xres * 4;
            $innerp = $xres * 2.5;
            $textw = ($bcw * 0.5) - $outerp - $innerp;
            $chars = 4;
            $charLO = '<'; // Left Outer
            $charLI = substr($code, 0, 4); // Left Inner
            $charRI = substr($code, 4, 4); // Right Inner
            if (!$supplement) {
                $charRO = '>'; // Right Outer
            }
        }

        $this->SetFontSize(($outerfontsize / 3) * 3 * $fh * $size * Mpdf::SCALE); // 3mm numerals (FontSize is larger to account for space above/below characters)

        if (!$this->usingCoreFont) {
            $cw = $this->_getCharWidth($this->CurrentFont['cw'], 32) * 3 * $fh * $size / 1000;
        } // character width at 3mm
        else {
            $cw = 600 * 3 * $fh * $size / 1000;
        } // mPDF 5.7.4
        // Outer left character
        $y_text = $y + $paddingT + $bch - ($num_height / 2);
        $y_text_outer = $y + $paddingT + $bch - ($num_height * ($outerfontsize / 3) / 2);

        $this->x = $x + $paddingL - ($cw * ($outerfontsize / 3) * 0.1); // 0.1 is correction as char does not fill full width;
        $this->y = $y_text_outer;
        $this->Cell($cw, $num_height, $charLO);

        // WORD SPACING for inner chars
        $xtra = $textw - ($cw * $chars);
        $charspacing = $xtra / ($chars - 1);
        if ($charspacing) {
            $this->_out(sprintf('BT %.3F Tc ET', $charspacing * Mpdf::SCALE));
        }

        if ($bgcol) {
            $this->SetFColor($bgcol);
        } else {
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
        }

        $this->SetFontSize(3 * $fh * $size * Mpdf::SCALE); // 3mm numerals (FontSize is larger to account for space above/below characters)
        // Inner left half characters
        $this->x = $x + $paddingL + $llm + $outerp;
        $this->y = $y_text;
        $this->Cell($textw, $num_height, $charLI, 0, 0, '', 1);

        // Inner right half characters
        $this->x = $x + $paddingL + $llm + ($bcw * 0.5) + $innerp;
        $this->y = $y_text;
        $this->Cell($textw, $num_height, $charRI, 0, 0, '', 1);

        if ($charspacing) {
            $this->_out('BT 0 Tc ET');
        }

        // Outer Right character
        $this->SetFontSize(($outerfontsize / 3) * 3 * $fh * $size * Mpdf::SCALE); // 3mm numerals (FontSize is larger to account for space above/below characters)

        $this->x = $x + $paddingL + $llm + $bcw + $rlm - ($cw * ($outerfontsize / 3) * 0.9); // 0.9 is correction as char does not fill full width
        $this->y = $y_text_outer;
        $this->Cell($cw * ($outerfontsize / 3), $num_height, $charRO, 0, 0, 'R');

        if ($supplement) { // EAN-2 or -5 Supplement
            // PRINT BARS
            $supparrcode = $this->barcode->getBarcodeArray($supplement_code, 'EAN' . $supplement);
            if ($supparrcode === false) {
                throw new \Mpdf\MpdfException('Error in barcode string (supplement): ' . $codestr . ' ' . $supplement_code);
            }
            if (strlen($supplement_code) != $supplement) {
                throw new \Mpdf\MpdfException('Barcode supplement incorrect: ' . $supplement_code);
            }
            $llm = $fbw - (($arrcode['lightmR'] - $supparrcode['sepM']) * $arrcode['nom-X'] * $size); // Left Light margin
            $rlm = $arrcode['lightmR'] * $arrcode['nom-X'] * $size; // Right Light margin

            $bcw = ($supparrcode["maxw"] * $xres); // Barcode width = Should always be 31.35mm * $size

            $fbw = $bcw + $llm + $rlm; // Full barcode width incl. light margins
            $ow = $fbw + $paddingL + $paddingR; // Full overall width incl. user-defined padding
            $bch = $fbh - (1.5 * $size) - ($num_height + 0.5);  // Barcode height of bars    (3mm for numerals)

            $xpos = $x + $paddingL + $llm;
            $ypos = $y + $paddingT + $num_height + 0.5;
            if ($col) {
                $this->SetFColor($col);
            } else {
                $this->SetFColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            }
            if ($supparrcode !== false) {
                foreach ($supparrcode["bcode"] as $v) {
                    $bw = ($v["w"] * $xres);
                    if ($v["t"]) {
                        // draw a vertical bar
                        $this->Rect($xpos, $ypos, $bw, $bch, 'F');
                    }
                    $xpos += $bw;
                }
            }

            // Characters
            if ($bgcol) {
                $this->SetFColor($bgcol);
            } else {
                $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
            }
            $this->SetFontSize(3 * $fh * $size * Mpdf::SCALE); // 3mm numerals (FontSize is larger to account for space above/below characters)
            $this->x = $x + $paddingL + $llm;
            $this->y = $y + $paddingT;
            $this->Cell($bcw, $num_height, $supplement_code, 0, 0, 'C');

            // Outer Right character (light margin)
            $this->SetFontSize(($outerfontsize / 3) * 3 * $fh * $size * Mpdf::SCALE); // 3mm numerals (FontSize is larger to account for space above/below characters)
            $this->x = $x + $paddingL + $llm + $bcw + $rlm - ($cw * 0.9); // 0.9 is correction as char does not fill full width
            $this->y = $y + $paddingT;
            $this->Cell($cw * ($outerfontsize / 3), $num_height, '>', 0, 0, 'R');
        }

        // Restore **************
        $this->SetFont($prevFontFamily, $prevFontStyle, $prevFontSizePt);
        $this->DrawColor = $prevDrawColor;
        $this->TextColor = $prevTextColor;
        $this->FillColor = $prevFillColor;
        $this->SetLineWidth($lw);
        $this->SetY($y);
    }

    // ====================================================
    // POSTAL and OTHER barcodes
    public function WriteBarcode2($code, $x = '', $y = '', $size = 1, $height = 1, $bgcol = false, $col = false, $btype = 'IMB', $print_ratio = '', $k = 1)
    {
        if (empty($code)) {
            return;
        }

        $this->barcode = new Barcode();
        $arrcode = $this->barcode->getBarcodeArray($code, $btype, $print_ratio);

        if (empty($x)) {
            $x = $this->x;
        }
        if (empty($y)) {
            $y = $this->y;
        }
        $prevDrawColor = $this->DrawColor;
        $prevTextColor = $this->TextColor;
        $prevFillColor = $this->FillColor;
        $lw = $this->LineWidth;
        $this->SetLineWidth(0.01);
        $size /= $k; // in case resized in a table
        $xres = $arrcode['nom-X'] * $size;

        if ($btype == 'IMB' || $btype == 'RM4SCC' || $btype == 'KIX' || $btype == 'POSTNET' || $btype == 'PLANET') {
            $llm = $arrcode['quietL'] / $k; // Left Quiet margin
            $rlm = $arrcode['quietR'] / $k; // Right Quiet margin
            $tlm = $blm = $arrcode['quietTB'] / $k;
            $height = 1;  // Overrides
        } elseif (in_array($btype, ['C128A', 'C128B', 'C128C', 'EAN128A', 'EAN128B', 'EAN128C', 'C39', 'C39+', 'C39E', 'C39E+', 'S25', 'S25+', 'I25', 'I25+', 'I25B', 'I25B+', 'C93', 'MSI', 'MSI+', 'CODABAR', 'CODE11'])) {
            $llm = $arrcode['lightmL'] * $xres; // Left Quiet margin
            $rlm = $arrcode['lightmR'] * $xres; // Right Quiet margin
            $tlm = $blm = $arrcode['lightTB'] * $xres * $height;
        }


        $bcw = ($arrcode["maxw"] * $xres);
        $fbw = $bcw + $llm + $rlm;  // Full barcode width incl. light margins

        $bch = ($arrcode["nom-H"] * $size * $height);
        $fbh = $bch + $tlm + $blm;  // Full barcode height
        // PRINT border background color
        $xpos = $x;
        $ypos = $y;
        if ($col) {
            $this->SetDColor($col);
            $this->SetTColor($col);
        } else {
            $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
            $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        }
        if ($bgcol) {
            $this->SetFColor($bgcol);
        } else {
            $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
        }

        // PRINT BARS
        if ($col) {
            $this->SetFColor($col);
        } else {
            $this->SetFColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        }
        $xpos = $x + $llm;

        if ($arrcode !== false) {
            foreach ($arrcode["bcode"] as $v) {
                $bw = ($v["w"] * $xres);
                if ($v["t"]) {
                    $ypos = $y + $tlm + ($bch * $v['p'] / $arrcode['maxh']);
                    $this->Rect($xpos, $ypos, $bw, ($v['h'] * $bch / $arrcode['maxh']), 'F');
                }
                $xpos += $bw;
            }
        }

        // PRINT BEARER BARS
        if ($btype == 'I25B' || $btype == 'I25B+') {
            $this->Rect($x, $y, $fbw, ($arrcode['lightTB'] * $xres * $height), 'F');
            $this->Rect($x, $y + $tlm + $bch, $fbw, ($arrcode['lightTB'] * $xres * $height), 'F');
        }

        // Restore **************
        $this->DrawColor = $prevDrawColor;
        $this->TextColor = $prevTextColor;
        $this->FillColor = $prevFillColor;
        $this->SetLineWidth($lw);
        $this->SetY($y);
    }

    /* -- END BARCODES -- */

    // ====================================================
    // ====================================================

    public function StartTransform($returnstring = false)
    {
        if ($returnstring) {
            return('q');
        } else {
            $this->_out('q');
        }
    }

    public function StopTransform($returnstring = false)
    {
        if ($returnstring) {
            return('Q');
        } else {
            $this->_out('Q');
        }
    }

    public function transformScale($s_x, $s_y, $x = '', $y = '', $returnstring = false)
    {
        if ($x === '') {
            $x = $this->x;
        }
        if ($y === '') {
            $y = $this->y;
        }
        if (($s_x == 0) or ( $s_y == 0)) {
            throw new \Mpdf\MpdfException('Please do not use values equal to zero for scaling');
        }
        $y = ($this->h - $y) * Mpdf::SCALE;
        $x *= Mpdf::SCALE;
        // calculate elements of transformation matrix
        $s_x /= 100;
        $s_y /= 100;
        $tm = [];
        $tm[0] = $s_x;
        $tm[1] = 0;
        $tm[2] = 0;
        $tm[3] = $s_y;
        $tm[4] = $x * (1 - $s_x);
        $tm[5] = $y * (1 - $s_y);
        // scale the coordinate system
        if ($returnstring) {
            return($this->_transform($tm, true));
        } else {
            $this->_transform($tm);
        }
    }

    public function transformTranslate($t_x, $t_y, $returnstring = false)
    {
        // calculate elements of transformation matrix
        $tm = [];
        $tm[0] = 1;
        $tm[1] = 0;
        $tm[2] = 0;
        $tm[3] = 1;
        $tm[4] = $t_x * Mpdf::SCALE;
        $tm[5] = -$t_y * Mpdf::SCALE;
        // translate the coordinate system
        if ($returnstring) {
            return($this->_transform($tm, true));
        } else {
            $this->_transform($tm);
        }
    }

    public function transformRotate($angle, $x = '', $y = '', $returnstring = false)
    {
        if ($x === '') {
            $x = $this->x;
        }
        if ($y === '') {
            $y = $this->y;
        }
        $angle = -$angle;
        $y = ($this->h - $y) * Mpdf::SCALE;
        $x *= Mpdf::SCALE;
        // calculate elements of transformation matrix
        $tm = [];
        $tm[0] = cos(deg2rad($angle));
        $tm[1] = sin(deg2rad($angle));
        $tm[2] = -$tm[1];
        $tm[3] = $tm[0];
        $tm[4] = $x + $tm[1] * $y - $tm[0] * $x;
        $tm[5] = $y - $tm[0] * $y - $tm[1] * $x;
        // rotate the coordinate system around ($x,$y)
        if ($returnstring) {
            return($this->_transform($tm, true));
        } else {
            $this->_transform($tm);
        }
    }

    // mPDF 5.7.3 TRANSFORMS
    public function transformSkew($angle_x, $angle_y, $x = '', $y = '', $returnstring = false)
    {
        if ($x === '') {
            $x = $this->x;
        }
        if ($y === '') {
            $y = $this->y;
        }
        $angle_x = -$angle_x;
        $angle_y = -$angle_y;
        $x *= Mpdf::SCALE;
        $y = ($this->h - $y) * Mpdf::SCALE;
        // calculate elements of transformation matrix
        $tm = [];
        $tm[0] = 1;
        $tm[1] = tan(deg2rad($angle_y));
        $tm[2] = tan(deg2rad($angle_x));
        $tm[3] = 1;
        $tm[4] = -$tm[2] * $y;
        $tm[5] = -$tm[1] * $x;
        // skew the coordinate system
        if ($returnstring) {
            return($this->_transform($tm, true));
        } else {
            $this->_transform($tm);
        }
    }

    // AUTOFONT =========================
    public function markScriptToLang($html)
    {
        if ($this->onlyCoreFonts) {
            return $html;
        }

        $n = '';
        $a = preg_split('/<(.*?)>/ms', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($a as $i => $e) {
            if ($i % 2 == 0) {
                // ignore if in Textarea
                if ($i > 0 && strtolower(substr($a[$i - 1], 1, 8)) == 'textarea') {
                    $a[$i] = $e;
                    continue;
                }
                $e = UtfString::strcode2utf($e);
                $e = $this->lesser_entity_decode($e);

                $earr = $this->UTF8StringToArray($e, false);

                $scriptblock = 0;
                $scriptblocks = [];
                $scriptblocks[0] = 0;
                $chardata = [];
                $subchunk = 0;
                $charctr = 0;
                foreach ($earr as $char) {
                    $ucd_record = Ucdn::get_ucd_record($char);
                    $sbl = $ucd_record[6];

                    if ($sbl && $sbl != 40 && $sbl != 102) {
                        if ($scriptblock == 0) {
                            $scriptblock = $sbl;
                            $scriptblocks[$subchunk] = $scriptblock;
                        } elseif ($scriptblock > 0 && $scriptblock != $sbl) {
                            // NEW (non-common) Script encountered in this chunk.
                            // Start a new subchunk
                            $subchunk++;
                            $scriptblock = $sbl;
                            $charctr = 0;
                            $scriptblocks[$subchunk] = $scriptblock;
                        }
                    }

                    $chardata[$subchunk][$charctr]['script'] = $sbl;
                    $chardata[$subchunk][$charctr]['uni'] = $char;
                    $charctr++;
                }

                // If scriptblock[x] = common & non-baseScript
                // and scriptblock[x+1] = baseScript
                // Move common script from end of x to start of x+1
                for ($sch = 0; $sch < $subchunk; $sch++) {
                    if ($scriptblocks[$sch] > 0 && $scriptblocks[$sch] != $this->baseScript && $scriptblocks[$sch + 1] == $this->baseScript) {
                        $end = count($chardata[$sch]) - 1;
                        while ($chardata[$sch][$end]['script'] == 0 && $end > 1) { // common script
                            $tmp = array_pop($chardata[$sch]);
                            array_unshift($chardata[$sch + 1], $tmp);
                            $end--;
                        }
                    }
                }

                $o = '';
                for ($sch = 0; $sch <= $subchunk; $sch++) {
                    if (isset($chardata[$sch])) {
                        $s = '';
                        for ($j = 0; $j < count($chardata[$sch]); $j++) {
                            $s .= UtfString::code2utf($chardata[$sch][$j]['uni']);
                        }
                        // ZZZ99 Undo lesser_entity_decode as above - but only for <>&
                        $s = str_replace("&", "&amp;", $s);
                        $s = str_replace("<", "&lt;", $s);
                        $s = str_replace(">", "&gt;", $s);

                        // Check Vietnamese if Latin script - even if Basescript
                        if ($scriptblocks[$sch] == Ucdn::SCRIPT_LATIN && $this->autoVietnamese && preg_match("/([" . $this->scriptToLanguage->getLanguageDelimiters('viet') . "])/u", $s)) {
                            $o .= '<span lang="vi" class="lang_vi">' . $s . '</span>';
                        } // Check Arabic for different languages if Arabic script - even if Basescript
                        elseif ($scriptblocks[$sch] == Ucdn::SCRIPT_ARABIC && $this->autoArabic) {
                            if (preg_match("/[" . $this->scriptToLanguage->getLanguageDelimiters('sindhi') . "]/u", $s)) {
                                $o .= '<span lang="sd" class="lang_sd">' . $s . '</span>';
                            } elseif (preg_match("/[" . $this->scriptToLanguage->getLanguageDelimiters('urdu') . "]/u", $s)) {
                                $o .= '<span lang="ur" class="lang_ur">' . $s . '</span>';
                            } elseif (preg_match("/[" . $this->scriptToLanguage->getLanguageDelimiters('pashto') . "]/u", $s)) {
                                $o .= '<span lang="ps" class="lang_ps">' . $s . '</span>';
                            } elseif (preg_match("/[" . $this->scriptToLanguage->getLanguageDelimiters('persian') . "]/u", $s)) {
                                $o .= '<span lang="fa" class="lang_fa">' . $s . '</span>';
                            } elseif ($this->baseScript != Ucdn::SCRIPT_ARABIC && $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch])) {
                                $o .= '<span lang="' . $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch]) . '" class="lang_' . $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch]) . '">' . $s . '</span>';
                            } else {
                                // Just output chars
                                $o .= $s;
                            }
                        } // Identify Script block if not Basescript, and mark up as language
                        elseif ($scriptblocks[$sch] > 0 && $scriptblocks[$sch] != $this->baseScript && $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch])) {
                            // Encase in <span>
                            $o .= '<span lang="' . $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch]) . '" class="lang_' . $this->scriptToLanguage->getLanguageByScript($scriptblocks[$sch]) . '">';
                            $o .= $s;
                            $o .= '</span>';
                        } else {
                            // Just output chars
                            $o .= $s;
                        }
                    }
                }

                $a[$i] = $o;
            } else {
                $a[$i] = '<' . $e . '>';
            }
        }
        $n = implode('', $a);

        return $n;
    }

    // ===========================
    // Functions
    // Call-back function Used for usort in fn _tableWrite

    public function mb_strrev($str, $enc = 'utf-8')
    {
        $ch = [];
        $ch = preg_split('//u', $str);
        $revch = array_reverse($ch);
        return implode('', $revch);
    }

    /* -- COLUMNS -- */

    // Callback function from function printcolumnbuffer in mpdf
    public function columnAdjustAdd($type, $k, $xadj, $yadj, $a, $b, $c = 0, $d = 0, $e = 0, $f = 0)
    {
        if ($type == 'Td') {  // xpos,ypos
            $a += ($xadj * $k);
            $b -= ($yadj * $k);
            return 'BT ' . sprintf('%.3F %.3F', $a, $b) . ' Td';
        } elseif ($type == 're') {  // xpos,ypos,width,height
            $a += ($xadj * $k);
            $b -= ($yadj * $k);
            return sprintf('%.3F %.3F %.3F %.3F', $a, $b, $c, $d) . ' re';
        } elseif ($type == 'l') {  // xpos,ypos,x2pos,y2pos
            $a += ($xadj * $k);
            $b -= ($yadj * $k);
            return sprintf('%.3F %.3F l', $a, $b);
        } elseif ($type == 'img') {  // width,height,xpos,ypos
            $c += ($xadj * $k);
            $d -= ($yadj * $k);
            return sprintf('q %.3F 0 0 %.3F %.3F %.3F', $a, $b, $c, $d) . ' cm /' . $e;
        } elseif ($type == 'draw') {  // xpos,ypos
            $a += ($xadj * $k);
            $b -= ($yadj * $k);
            return sprintf('%.3F %.3F m', $a, $b);
        } elseif ($type == 'bezier') {  // xpos,ypos,x2pos,y2pos,x3pos,y3pos
            $a += ($xadj * $k);
            $b -= ($yadj * $k);
            $c += ($xadj * $k);
            $d -= ($yadj * $k);
            $e += ($xadj * $k);
            $f -= ($yadj * $k);
            return sprintf('%.3F %.3F %.3F %.3F %.3F %.3F', $a, $b, $c, $d, $e, $f) . ' c';
        }
    }

    /* -- END COLUMNS -- */

    // mPDF 5.7.3 TRANSFORMS
    public function ConvertAngle($s, $makepositive = true)
    {
        if (preg_match('/([\-]*[0-9\.]+)(deg|grad|rad)/i', $s, $m)) {
            $angle = $m[1] + 0;
            if (strtolower($m[2]) == 'deg') {
                $angle = $angle;
            } elseif (strtolower($m[2]) == 'grad') {
                $angle *= (360 / 400);
            } elseif (strtolower($m[2]) == 'rad') {
                $angle = rad2deg($angle);
            }
            while ($angle >= 360) {
                $angle -= 360;
            }
            while ($angle <= -360) {
                $angle += 360;
            }
            if ($makepositive) { // always returns an angle between 0 and 360deg
                if ($angle < 0) {
                    $angle += 360;
                }
            }
        } else {
            $angle = $s + 0;
        }
        return $angle;
    }

    public function lesser_entity_decode($html)
    {
        // supports the most used entity codes (only does ascii safe characters)
        $html = str_replace("&lt;", "<", $html);
        $html = str_replace("&gt;", ">", $html);

        $html = str_replace("&apos;", "'", $html);
        $html = str_replace("&quot;", '"', $html);
        $html = str_replace("&amp;", "&", $html);
        return $html;
    }

    public function AdjustHTML($html, $tabSpaces = 8)
    {
        $limit = ini_get('pcre.backtrack_limit');
        if (strlen($html) > $limit) {
            throw new \Mpdf\MpdfException(sprintf(
                'The HTML code size is larger than pcre.backtrack_limit %d. You should use WriteHTML() with smaller string lengths.',
                $limit
            ));
        }

        preg_match_all("/(<annotation.*?>)/si", $html, $m);
        if (count($m[1])) {
            for ($i = 0; $i < count($m[1]); $i++) {
                $sub = preg_replace("/\n/si", "\xbb\xa4\xac", $m[1][$i]);
                $html = preg_replace('/' . preg_quote($m[1][$i], '/') . '/si', $sub, $html);
            }
        }

        preg_match_all("/(<svg.*?<\/svg>)/si", $html, $svgi);
        if (count($svgi[0])) {
            for ($i = 0; $i < count($svgi[0]); $i++) {
                $file = $this->cache->write('/_tempSVG' . uniqid(random_int(1, 100000), true) . '_' . $i . '.svg', $svgi[0][$i]);
                $html = str_replace($svgi[0][$i], '<img src="' . $file . '" />', $html);
            }
        }

        // Remove javascript code from HTML (should not appear in the PDF file)
        $html = preg_replace('/<script.*?<\/script>/is', '', $html);

        // Remove special comments
        $html = preg_replace('/<!--mpdf/i', '', $html);
        $html = preg_replace('/mpdf-->/i', '', $html);

        // Remove comments from HTML (should not appear in the PDF file)
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        $html = preg_replace('/\f/', '', $html); // replace formfeed by nothing
        $html = preg_replace('/\r/', '', $html); // replace carriage return by nothing
        // Well formed XHTML end tags
        $html = preg_replace('/<(br|hr)>/i', "<\\1 />", $html); // mPDF 6
        $html = preg_replace('/<(br|hr)\/>/i', "<\\1 />", $html);
        // Get rid of empty <thead></thead> etc
        $html = preg_replace('/<tr>\s*<\/tr>/i', '', $html);
        $html = preg_replace('/<thead>\s*<\/thead>/i', '', $html);
        $html = preg_replace('/<tfoot>\s*<\/tfoot>/i', '', $html);
        $html = preg_replace('/<table[^>]*>\s*<\/table>/i', '', $html);

        // Remove spaces at end of table cells
        $html = preg_replace("/[ \n\r]+<\/t(d|h)/", '</t\\1', $html);

        $html = preg_replace("/[ ]*<dottab\s*[\/]*>[ ]*/", '<dottab />', $html);

        // Concatenates any Substitute characters from symbols/dingbats
        $html = str_replace('</tts><tts>', '|', $html);
        $html = str_replace('</ttz><ttz>', '|', $html);
        $html = str_replace('</tta><tta>', '|', $html);

        $html = preg_replace('/<br \/>\s*/is', "<br />", $html);

        $html = preg_replace('/<wbr[ \/]*>\s*/is', "&#173;", $html);

        // Preserve '\n's in content between the tags <pre> and </pre>
        if (preg_match('/<pre/', $html)) {
            $html_a = preg_split('/(\<\/?pre[^\>]*\>)/', $html, -1, 2);
            $h = [];
            $c = 0;
            foreach ($html_a as $s) {
                if ($c > 1 && preg_match('/^<\/pre/i', $s)) {
                    $c--;
                    $s = preg_replace('/<\/pre/i', '</innerpre', $s);
                } elseif ($c > 0 && preg_match('/^<pre/i', $s)) {
                    $c++;
                    $s = preg_replace('/<pre/i', '<innerpre', $s);
                } elseif (preg_match('/^<pre/i', $s)) {
                    $c++;
                } elseif (preg_match('/^<\/pre/i', $s)) {
                    $c--;
                }
                array_push($h, $s);
            }
            $html = implode("", $h);
        }
        $thereispre = preg_match_all('#<pre(.*?)>(.*?)</pre>#si', $html, $temp);
        // Preserve '\n's in content between the tags <textarea> and </textarea>
        $thereistextarea = preg_match_all('#<textarea(.*?)>(.*?)</textarea>#si', $html, $temp2);
        $html = preg_replace('/[\n]/', ' ', $html); // replace linefeed by spaces
        $html = preg_replace('/[\t]/', ' ', $html); // replace tabs by spaces
        // Converts < to &lt; when not a tag
        $html = preg_replace('/<([^!\/a-zA-Z_:])/i', '&lt;\\1', $html); // mPDF 5.7.3
        $html = preg_replace("/[ ]+/", ' ', $html);

        $html = preg_replace('/\/li>\s+<\/(u|o)l/i', '/li></\\1l', $html);
        $html = preg_replace('/\/(u|o)l>\s+<\/li/i', '/\\1l></li', $html);
        $html = preg_replace('/\/li>\s+<\/(u|o)l/i', '/li></\\1l', $html);
        $html = preg_replace('/\/li>\s+<li/i', '/li><li', $html);
        $html = preg_replace('/<(u|o)l([^>]*)>[ ]+/i', '<\\1l\\2>', $html);
        $html = preg_replace('/[ ]+<(u|o)l/i', '<\\1l', $html);

        // Make self closing tabs valid XHTML
        // Tags which are self-closing: 1) Replaceable and 2) Non-replaced items
        $selftabs = 'input|hr|img|br|barcode|dottab';
        $selftabs2 = 'indexentry|indexinsert|bookmark|watermarktext|watermarkimage|column_break|columnbreak|newcolumn|newpage|page_break|pagebreak|formfeed|columns|toc|tocpagebreak|setpageheader|setpagefooter|sethtmlpageheader|sethtmlpagefooter|annotation';

        // Fix self-closing tags which don't close themselves
        $html = preg_replace('/(<(' . $selftabs . '|' . $selftabs2 . ')[^>\/]*)>/i', '\\1 />', $html);

        // Fix self-closing tags that don't include a space between the tag name and the closing slash
        $html = preg_replace('/(<(' . $selftabs . '|' . $selftabs2 . '))\/>/i', '\\1 />', $html);

        $iterator = 0;
        while ($thereispre) { // Recover <pre attributes>content</pre>
            $temp[2][$iterator] = preg_replace('/<([^!\/a-zA-Z_:])/', '&lt;\\1', $temp[2][$iterator]); // mPDF 5.7.2    // mPDF 5.7.3

            $temp[2][$iterator] = preg_replace_callback("/^([^\n\t]*?)\t/m", [$this, 'tabs2spaces_callback'], $temp[2][$iterator]); // mPDF 5.7+
            $temp[2][$iterator] = preg_replace('/\t/', str_repeat(" ", $tabSpaces), $temp[2][$iterator]);

            $temp[2][$iterator] = preg_replace('/\n/', "<br />", $temp[2][$iterator]);
            $temp[2][$iterator] = str_replace('\\', "\\\\", $temp[2][$iterator]);
            // $html = preg_replace('#<pre(.*?)>(.*?)</pre>#si','<erp'.$temp[1][$iterator].'>'.$temp[2][$iterator].'</erp>',$html,1);
            $html = preg_replace('#<pre(.*?)>(.*?)</pre>#si', '<erp' . $temp[1][$iterator] . '>' . str_replace('$', '\$', $temp[2][$iterator]) . '</erp>', $html, 1); // mPDF 5.7+
            $thereispre--;
            $iterator++;
        }
        $iterator = 0;
        while ($thereistextarea) { // Recover <textarea attributes>content</textarea>
            $temp2[2][$iterator] = preg_replace('/\t/', str_repeat(" ", $tabSpaces), $temp2[2][$iterator]);
            $temp2[2][$iterator] = str_replace('\\', "\\\\", $temp2[2][$iterator]);
            $html = preg_replace('#<textarea(.*?)>(.*?)</textarea>#si', '<aeratxet' . $temp2[1][$iterator] . '>' . trim($temp2[2][$iterator]) . '</aeratxet>', $html, 1);
            $thereistextarea--;
            $iterator++;
        }
        // Restore original tag names
        $html = str_replace("<erp", "<pre", $html);
        $html = str_replace("</erp>", "</pre>", $html);
        $html = str_replace("<aeratxet", "<textarea", $html);
        $html = str_replace("</aeratxet>", "</textarea>", $html);
        $html = str_replace("</innerpre", "</pre", $html);
        $html = str_replace("<innerpre", "<pre", $html);

        $html = preg_replace('/<textarea([^>]*)><\/textarea>/si', '<textarea\\1> </textarea>', $html);
        $html = preg_replace('/(<table[^>]*>)\s*(<caption)(.*?<\/caption>)(.*?<\/table>)/si', '\\2 position="top"\\3\\1\\4\\2 position="bottom"\\3', $html); // *TABLES*
        $html = preg_replace('/<(h[1-6])([^>]*)(>(?:(?!h[1-6]).)*?<\/\\1>\s*<table)/si', '<\\1\\2 keep-with-table="1"\\3', $html); // *TABLES*
        $html = preg_replace("/\xbb\xa4\xac/", "\n", $html);

        // Fixes <p>&#8377</p> which browser copes with even though it is wrong!
        $html = preg_replace("/(&#[x]{0,1}[0-9a-f]{1,5})</i", "\\1;<", $html);
        return $html;
    }

    // mPDF 5.7+
    public function tabs2spaces_callback($matches)
    {
        return (stripslashes($matches[1]) . str_repeat(' ', $this->tabSpaces - (mb_strlen(stripslashes($matches[1])) % $this->tabSpaces)));
    }

    // mPDF 5.7+
    public function date_callback($matches)
    {
        return date($matches[1]);
    }

    // ===========================
    /* -- IMPORTS -- */
    public function SetImportUse()
    {
        if (!class_exists('fpdi_pdf_parser')) {
            throw new \Mpdf\MpdfException('Class fpdi_pdf_parser not found. Please run composer update or require setasign/fpdi 1.6.* manually');
        }

        $this->enableImports = true;
    }

    // from mPDFI
    public function hex2str($hex)
    {
        return pack("H*", str_replace(["\r", "\n", " "], "", $hex));
    }

    public function str2hex($str)
    {
        return current(unpack("H*", $str));
    }

    public function pdf_write_value(&$value)
    {
        switch ($value[0]) {
            case pdf_parser::TYPE_TOKEN:
                $this->_out($value[1] . ' ', false);
                break;

            case pdf_parser::TYPE_NUMERIC:
            case pdf_parser::TYPE_REAL:
                if (is_float($value[1]) && $value[1] != 0) {
                    $this->_out(rtrim(rtrim(sprintf('%F', $value[1]), '0'), '.') . ' ', false);
                } else {
                    $this->_out($value[1] . ' ', false);
                }
                break;

            case pdf_parser::TYPE_ARRAY:
                // An array. Output the proper
                // structure and move on.
                $this->_out("[", false);
                for ($i = 0; $i < count($value[1]); $i++) {
                    $this->pdf_write_value($value[1][$i]);
                }
                $this->_out("]");
                break;

            case pdf_parser::TYPE_DICTIONARY:
                // A dictionary.
                $this->_out("<<", false);

                foreach ($value[1] as $k => $v) {
                    $this->_out($k . ' ', false);
                    $this->pdf_write_value($v);
                }

                $this->_out(">>");
                break;

            case pdf_parser::TYPE_OBJREF:
                // An indirect object reference
                // Fill the object stack if needed
                $cpfn = $this->current_parser->filename;
                if (!isset($this->_don_obj_stack[$cpfn][$value[1]])) {
                    $this->_newobj(false, true);
                    $this->_obj_stack[$cpfn][$value[1]] = [$this->n, $value];
                    $this->_don_obj_stack[$cpfn][$value[1]] = [$this->n, $value];
                }
                $objid = $this->_don_obj_stack[$cpfn][$value[1]][0];
                $this->_out("{$objid} 0 R"); // {$value[2]}
                break;

            case pdf_parser::TYPE_STRING:
                if ($this->encrypted) {
                    $value[1] = $this->_unescape($value[1]);
                    $value[1] = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $value[1]);
                    $value[1] = $this->_escape($value[1]);
                }
                // A string.
                $this->_out('(' . $value[1] . ')');
                break;

            case pdf_parser::TYPE_STREAM:
                // A stream. First, output the
                // stream dictionary, then the
                // stream data itself.
                $this->pdf_write_value($value[1]);
                if ($this->encrypted) {
                    $value[2][1] = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $value[2][1]);
                }
                $this->_out("stream");
                $this->_out($value[2][1]);
                $this->_out("endstream");
                break;

            case pdf_parser::TYPE_HEX:
                if ($this->encrypted) {
                    $value[1] = $this->hex2str($value[1]);
                    $value[1] = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $value[1]);
                    // remake hexstring of encrypted string
                    $value[1] = $this->str2hex($value[1]);
                }
                $this->_out("<" . $value[1] . ">");
                break;

            case pdf_parser::TYPE_BOOLEAN:
                $this->_out($value[1] ? 'true' : 'false');
                break;

            case pdf_parser::TYPE_NULL:
                // The null object.
                $this->_out("null");
                break;
        }
    }

    // ========== OVERWRITE SEARCH STRING IN A PDF FILE ================
    public function OverWrite($file_in, $search, $replacement, $dest = Destination::DOWNLOAD, $file_out = "mpdf")
    {
        $pdf = file_get_contents($file_in);

        if (!is_array($search)) {
            $x = $search;
            $search = [$x];
        }
        if (!is_array($replacement)) {
            $x = $replacement;
            $replacement = [$x]; // mPDF 5.7.4
        }

        if (!$this->onlyCoreFonts && !$this->usingCoreFont) {
            foreach ($search as $k => $val) {
                $search[$k] = $this->UTF8ToUTF16BE($search[$k], false);
                $search[$k] = $this->_escape($search[$k]);
                $replacement[$k] = $this->UTF8ToUTF16BE($replacement[$k], false);
                $replacement[$k] = $this->_escape($replacement[$k]);
            }
        } else {
            foreach ($replacement as $k => $val) {
                $replacement[$k] = mb_convert_encoding($replacement[$k], $this->mb_enc, 'utf-8');
                $replacement[$k] = $this->_escape($replacement[$k]);
            }
        }

        // Get xref into array
        $xref = [];
        preg_match("/xref\n0 (\d+)\n(.*?)\ntrailer/s", $pdf, $m);
        $xref_objid = $m[1];
        preg_match_all('/(\d{10}) (\d{5}) (f|n)/', $m[2], $x);
        for ($i = 0; $i < count($x[0]); $i++) {
            $xref[] = [intval($x[1][$i]), $x[2][$i], $x[3][$i]];
        }

        $changes = [];
        preg_match("/<<\s*\/Type\s*\/Pages\s*\/Kids\s*\[(.*?)\]\s*\/Count/s", $pdf, $m);
        preg_match_all("/(\d+) 0 R /s", $m[1], $o);
        $objlist = $o[1];

        foreach ($objlist as $obj) {
            if ($this->compress) {
                preg_match("/" . ($obj + 1) . " 0 obj\n<<\s*\/Filter\s*\/FlateDecode\s*\/Length (\d+)>>\nstream\n(.*?)\nendstream\n/s", $pdf, $m);
            } else {
                preg_match("/" . ($obj + 1) . " 0 obj\n<<\s*\/Length (\d+)>>\nstream\n(.*?)\nendstream\n/s", $pdf, $m);
            }

            $s = $m[2];
            if (!$s) {
                continue;
            }

            $oldlen = $m[1];

            if ($this->encrypted) {
                $s = $this->protection->rc4($this->protection->objectKey($obj + 1), $s);
            }

            if ($this->compress) {
                $s = gzuncompress($s);
            }

            foreach ($search as $k => $val) {
                $s = str_replace($search[$k], $replacement[$k], $s);
            }

            if ($this->compress) {
                $s = gzcompress($s);
            }

            if ($this->encrypted) {
                $s = $this->protection->rc4($this->protection->objectKey($obj + 1), $s);
            }

            $newlen = strlen($s);

            $changes[($xref[$obj + 1][0])] = ($newlen - $oldlen) + (strlen($newlen) - strlen($oldlen));

            if ($this->compress) {
                $newstr = ($obj + 1) . " 0 obj\n<</Filter /FlateDecode /Length " . $newlen . ">>\nstream\n" . $s . "\nendstream\n";
            } else {
                $newstr = ($obj + 1) . " 0 obj\n<</Length " . $newlen . ">>\nstream\n" . $s . "\nendstream\n";
            }

            $pdf = str_replace($m[0], $newstr, $pdf);
        }

        // Update xref in PDF
        krsort($changes);
        $newxref = "xref\n0 " . $xref_objid . "\n";
        foreach ($xref as $v) {
            foreach ($changes as $ck => $cv) {
                if ($v[0] > $ck) {
                    $v[0] += $cv;
                }
            }
            $newxref .= sprintf('%010d', $v[0]) . ' ' . $v[1] . ' ' . $v[2] . " \n";
        }
        $newxref .= "trailer";
        $pdf = preg_replace("/xref\n0 \d+\n.*?\ntrailer/s", $newxref, $pdf);

        // Update startxref in PDF
        preg_match("/startxref\n(\d+)\n%%EOF/s", $pdf, $m);
        $startxref = $m[1];
        $startxref += array_sum($changes);
        $pdf = preg_replace("/startxref\n(\d+)\n%%EOF/s", "startxref\n" . $startxref . "\n%%EOF", $pdf);

        // OUTPUT
        switch ($dest) {
            case Destination::INLINE:
                if (isset($_SERVER['SERVER_NAME'])) {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-disposition: inline; filename=' . $file_out);
                }

                echo $pdf;

                break;

            case Destination::FILE:
                if (!$file_out) {
                    $file_out = 'mpdf.pdf';
                }

                $f = fopen($file_out, 'wb');

                if (!$f) {
                    throw new \Mpdf\MpdfException('Unable to create output file: ' . $file_out);
                }

                fwrite($f, $pdf, strlen($pdf));

                fclose($f);

                break;

            case Destination::STRING_RETURN:
                return $pdf;

            case Destination::DOWNLOAD: // Download file
            default:
                if (isset($_SERVER['HTTP_USER_AGENT']) and strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
                    header('Content-Type: application/force-download');
                } else {
                    header('Content-Type: application/octet-stream');
                }

                header('Content-Length: ' . strlen($pdf));
                header('Content-disposition: attachment; filename=' . $file_out);

                echo $pdf;

                break;
        }
    }

    public function GetTemplateSize($tplidx, $_w = 0, $_h = 0)
    {
        if (!$this->tpls[$tplidx]) {
            return false;
        }
        $w = $this->tpls[$tplidx]['box']['w'];
        $h = $this->tpls[$tplidx]['box']['h'];
        if ($_w == 0 and $_h == 0) {
            $_w = $w;
            $_h = $h;
        }
        if ($_w == 0) {
            $_w = $_h * $w / $h;
        }
        if ($_h == 0) {
            $_h = $_w * $h / $w;
        }
        return ["w" => $_w, "h" => $_h];
    }


    public function Thumbnail($file, $npr = 3, $spacing = 10)
    {
        // $npr = number per row
        $w = (($this->pgwidth + $spacing) / $npr) - $spacing;
        $oldlinewidth = $this->LineWidth;
        $this->SetLineWidth(0.02);
        $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
        $h = 0;
        $maxh = 0;
        $x = $_x = $this->lMargin;
        $_y = $this->tMargin;

        if ($this->y == 0) {
            $y = $_y;
        } else {
            $y = $this->y;
        }

        $pagecount = $this->SetSourceFile($file);

        for ($n = 1; $n <= $pagecount; $n++) {
            $tplidx = $this->ImportPage($n);
            $size = $this->UseTemplate($tplidx, $x, $y, $w);
            $this->Rect($x, $y, $size['w'], $size['h']);
            $h = max($h, $size['h']);
            $maxh = max($h, $maxh);

            if ($n % $npr == 0) {
                if (($y + $h + $spacing + $maxh) > $this->PageBreakTrigger && $n != $pagecount) {
                    $this->AddPage();
                    $x = $_x;
                    $y = $_y;
                } else {
                    $y += $h + $spacing;
                    $x = $_x;
                    $h = 0;
                }
            } else {
                $x += $w + $spacing;
            }
        }
        $this->SetLineWidth($oldlinewidth);
    }

    public function SetSourceFile($filename)
    {
        $this->current_filename = $filename;
        $fn = $this->current_filename;
        if (!isset($this->parsers[$fn])) {
            try {
                $this->parsers[$fn] = new fpdi_pdf_parser($fn);
            } catch (\Exception $e) {
                throw new \Mpdf\MpdfException($e->getMessage());
            }
        }

        $this->current_parser = $this->parsers[$fn];
        return $this->parsers[$fn]->getPageCount();
    }

    public function ImportPage($pageno = 1, $crop_x = null, $crop_y = null, $crop_w = 0, $crop_h = 0, $boxName = '/CropBox')
    {
        $fn     = $this->current_filename;
        $parser = $this->parsers[$fn];
        $parser->setPageno($pageno);

        $this->tpl++;
        $this->tpls[$this->tpl] = [];
        $tpl = & $this->tpls[$this->tpl];
        $tpl['parser'] = $parser;
        $tpl['resources'] = $parser->getPageResources();
        $tpl['buffer'] = $parser->getContent();

        if (!in_array($boxName, $parser->availableBoxes)) {
            throw new \Mpdf\MpdfException(sprintf("Unknown box: %s", $boxName));
        }

        $pageboxes = $parser->getPageBoxes($pageno, Mpdf::SCALE);

        /**
         * MediaBox
         * CropBox: Default -> MediaBox
         * BleedBox: Default -> CropBox
         * TrimBox: Default -> CropBox
         * ArtBox: Default -> CropBox
         */
        if (!isset($pageboxes[$boxName]) && ($boxName == "/BleedBox" || $boxName == "/TrimBox" || $boxName == "/ArtBox")) {
            $boxName = "/CropBox";
        }

        if (!isset($pageboxes[$boxName]) && $boxName == "/CropBox") {
            $boxName = "/MediaBox";
        }

        if (!isset($pageboxes[$boxName])) {
            return false;
        }

        $box = $pageboxes[$boxName];

        $tpl['box'] = $box;
        // To build an array that can be used by useTemplate()
        $this->tpls[$this->tpl] = array_merge($this->tpls[$this->tpl], $box);
        // An imported page will start at 0,0 everytime. Translation will be set in _putformxobjects()
        $tpl['x'] = 0;
        $tpl['y'] = 0;
        $tpl['w'] = $tpl['box']['w'];
        $tpl['h'] = $tpl['box']['h'];

        if ($crop_w) {
            $tpl['box']['w'] = $crop_w;
        }
        if ($crop_h) {
            $tpl['box']['h'] = $crop_h;
        }
        if (isset($crop_x)) {
            $tpl['box']['x'] = $crop_x;
        }
        if (isset($crop_y)) {
            $tpl['box']['y'] = $tpl['h'] - $crop_y - $crop_h;
        }

        // fix for rotated pages
        $rotation = $parser->getPageRotation($pageno);

        if (isset($rotation[1]) && ($angle = $rotation[1] % 360) != 0 && $tpl['box']['w'] == $tpl['w']) {
            $steps = $angle / 90;

            $_w = $tpl['w'];
            $_h = $tpl['h'];
            $tpl['w'] = $steps % 2 == 0 ? $_w : $_h;
            $tpl['h'] = $steps % 2 == 0 ? $_h : $_w;
            if ($steps % 2 != 0) {
                $x = $y = ($steps == 1 || $steps == -3) ? $tpl['h'] : $tpl['w'];
            } else {
                $x = $tpl['w'];
                $y = $tpl['h'];
            }
            $cx = ($x / 2 + $tpl['box']['x']) * Mpdf::SCALE;
            $cy = ($y / 2 + $tpl['box']['y']) * Mpdf::SCALE;
            $angle*=-1;
            $angle*=M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $tpl['box']['w'] = $tpl['w'];
            $tpl['box']['h'] = $tpl['h'];
            $tpl['buffer'] = sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm %s Q', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy, $tpl['buffer']);
        }

        return $this->tpl;
    }

    public function UseTemplate($tplidx, $_x = null, $_y = null, $_w = 0, $_h = 0)
    {
        if (!isset($this->tpls[$tplidx])) {
            throw new \Mpdf\MpdfException("Template does not exist!");
        }

        if ($this->state == 0) {
            $this->AddPage();
        }

        $out = 'q 0 J 1 w 0 j 0 G' . "\n"; // reset standard values
        $x = $this->tpls[$tplidx]['x'];
        $y = $this->tpls[$tplidx]['y'];
        $w = $this->tpls[$tplidx]['w'];
        $h = $this->tpls[$tplidx]['h'];

        if ($_x == null) {
            $_x = $x;
        }

        if ($_y == null) {
            $_y = $y;
        }

        if ($_x === -1) {
            $_x = $this->x;
        }

        if ($_y === -1) {
            $_y = $this->y;
        }

        $wh = $this->GetTemplateSize($tplidx, $_w, $_h);
        $_w = $wh['w'];
        $_h = $wh['h'];
        $out .= sprintf("q %.4F 0 0 %.4F %.2F %.2F cm", ($_w / $this->tpls[$tplidx]['box']['w']), ($_h / $this->tpls[$tplidx]['box']['h']), $_x * Mpdf::SCALE, ($this->h - ($_y + $_h)) * Mpdf::SCALE) . "\n";
        $out .= $this->tplprefix . $tplidx . " Do Q\n";

        $s = ["w" => $_w, "h" => $_h];
        $out .= "Q\n";
        $this->pages[$this->page] = $out . $this->pages[$this->page];
        return $s;
    }

    public function SetPageTemplate($tplidx = '')
    {
        if (!isset($this->tpls[$tplidx])) {
            $this->pageTemplate = '';
            return false;
        }
        $this->pageTemplate = $tplidx;
    }

    public function SetDocTemplate($file = '', $continue = 0)
    {
        $this->docTemplate = $file;
        $this->docTemplateContinue = $continue;
    }

    /* -- END IMPORTS -- */

    public function SetJS($script)
    {
        $this->js = $script;
    }

    /**
     * This function takes the last comma or dot (if any) to make a clean float, ignoring thousand separator, currency or any other letter
     *
     * @param string $num
     * @see http://php.net/manual/de/function.floatval.php#114486
     * @return float
     */
    public function toFloat($num)
    {
        $dotPos = strrpos($num, '.');
        $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

        if (!$sep) {
            return floatval(preg_replace('/[^0-9]/', '', $num));
        }

        return floatval(
            preg_replace('/[^0-9]/', '', substr($num, 0, $sep)) . '.' .
            preg_replace('/[^0-9]/', '', substr($num, $sep+1, strlen($num)))
        );
    }

    public function getFontDescriptor()
    {
        return $this->fontDescriptor;
    }

}
