<?php

namespace Mpdf;

use pdf_parser;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

use Mpdf\Color\ColorConverter;
use Mpdf\Color\ColorModeConverter;
use Mpdf\Color\ColorSpaceRestrictor;

use Mpdf\Conversion;

use Mpdf\Css\Border;
use Mpdf\Css\TextVars;

use Mpdf\Image\ImageProcessor;

use Mpdf\Fonts\FontCache;
use Mpdf\Fonts\FontFileFinder;

use Mpdf\Utils\PdfDate;
use Mpdf\Utils\UtfString;

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
abstract class MpdfImpl implements \Psr\Log\LoggerAwareInterface
{

	const VERSION = '7.0.4';

	const SCALE = 72 / 25.4;

	var $useFixedNormalLineHeight; // mPDF 6
	var $useFixedTextBaseline; // mPDF 6
	var $adjustFontDescLineheight; // mPDF 6
	var $interpolateImages; // mPDF 6
	var $defaultPagebreakType; // mPDF 6 pagebreaktype
	var $indexUseSubentries; // mPDF 6

	var $autoScriptToLang; // mPDF 6
	var $baseScript; // mPDF 6
	var $autoVietnamese; // mPDF 6
	var $autoArabic; // mPDF 6

	var $CJKforceend;
	var $h2bookmarks;
	var $h2toc;
	var $decimal_align;
	var $margBuffer;
	var $splitTableBorderWidth;

	var $bookmarkStyles;
	var $useActiveForms;

	var $repackageTTF;
	var $allowCJKorphans;
	var $allowCJKoverflow;

	var $useKerning;
	var $restrictColorSpace;
	var $bleedMargin;
	var $crossMarkMargin;
	var $cropMarkMargin;
	var $cropMarkLength;
	var $nonPrintMargin;

	var $PDFX;
	var $PDFXauto;

	var $PDFA;
	var $PDFAversion = '1-B';
	var $PDFAauto;
	var $ICCProfile;

	var $printers_info;
	var $iterationCounter;
	var $smCapsScale;
	var $smCapsStretch;

	var $backupSubsFont;
	var $backupSIPFont;
	var $fonttrans;
	var $debugfonts;
	var $useAdobeCJK;
	var $percentSubset;
	var $maxTTFFilesize;
	var $BMPonly;

	var $tableMinSizePriority;

	var $dpi;
	var $watermarkImgAlphaBlend;
	var $watermarkImgBehind;
	var $justifyB4br;
	var $packTableData;
	var $pgsIns;
	var $simpleTables;
	var $enableImports;

	var $debug;

	var $setAutoTopMargin;
	var $setAutoBottomMargin;
	var $autoMarginPadding;
	var $collapseBlockMargins;
	var $falseBoldWeight;
	var $normalLineheight;
	var $incrementFPR1;
	var $incrementFPR2;
	var $incrementFPR3;
	var $incrementFPR4;

	var $SHYlang;
	var $SHYleftmin;
	var $SHYrightmin;
	var $SHYcharmin;
	var $SHYcharmax;
	var $SHYlanguages;

	// PageNumber Conditional Text
	var $pagenumPrefix;
	var $pagenumSuffix;

	var $nbpgPrefix;
	var $nbpgSuffix;
	var $showImageErrors;
	var $allow_output_buffering;
	var $autoPadding;
	var $tabSpaces;
	var $autoLangToFont;
	var $watermarkTextAlpha;
	var $watermarkImageAlpha;
	var $watermark_size;
	var $watermark_pos;
	var $annotSize;
	var $annotMargin;
	var $annotOpacity;
	var $title2annots;
	var $keepColumns;
	var $keep_table_proportions;
	var $ignore_table_widths;
	var $ignore_table_percents;
	var $list_number_suffix;

	var $list_auto_mode; // mPDF 6
	var $list_indent_first_level; // mPDF 6
	var $list_indent_default; // mPDF 6
	var $list_marker_offset; // mPDF 6
	var $list_symbol_size;

	var $useSubstitutions;
	var $CSSselectMedia;

	var $forcePortraitHeaders;
	var $forcePortraitMargins;
	var $displayDefaultOrientation;
	var $ignore_invalid_utf8;
	var $allowedCSStags;
	var $onlyCoreFonts;
	var $allow_charset_conversion;

	var $jSWord;
	var $jSmaxChar;
	var $jSmaxCharLast;
	var $jSmaxWordLast;

	var $max_colH_correction;

	var $table_error_report;
	var $table_error_report_param;
	var $biDirectional;
	var $text_input_as_HTML;
	var $anchor2Bookmark;
	var $shrink_tables_to_fit;

	var $allow_html_optional_endtags;

	var $img_dpi;

	var $defaultheaderfontsize;
	var $defaultheaderfontstyle;
	var $defaultheaderline;
	var $defaultfooterfontsize;
	var $defaultfooterfontstyle;
	var $defaultfooterline;
	var $header_line_spacing;
	var $footer_line_spacing;

	var $pregCJKchars;
	var $pregRTLchars;
	var $pregCURSchars; // mPDF 6

	var $mirrorMargins;
	var $watermarkText;
	var $watermarkAngle;
	var $watermarkImage;
	var $showWatermarkText;
	var $showWatermarkImage;

	var $svgAutoFont;
	var $svgClasses;

	var $fontsizes;

	var $defaultPageNumStyle; // mPDF 6

	//////////////////////
	// INTERNAL VARIABLES
	//////////////////////
	var $extrapagebreak; // mPDF 6 pagebreaktype

	var $uniqstr; // mPDF 5.7.2
	var $hasOC;

	var $textvar; // mPDF 5.7.1
	var $fontLanguageOverride; // mPDF 5.7.1
	var $OTLtags; // mPDF 5.7.1
	var $OTLdata;  // mPDF 5.7.1

	var $writingToC;
	var $layers;
	var $layerDetails;
	var $current_layer;
	var $open_layer_pane;
	var $decimal_offset;
	var $inMeter;

	var $CJKleading;
	var $CJKfollowing;
	var $CJKoverflow;

	var $textshadow;

	var $colsums;
	var $spanborder;
	var $spanborddet;

	var $visibility;

	var $kerning;
	var $fixedlSpacing;
	var $minwSpacing;
	var $lSpacingCSS;
	var $wSpacingCSS;

	var $spotColorIDs;
	var $SVGcolors;
	var $spotColors;
	var $defTextColor;
	var $defDrawColor;
	var $defFillColor;

	var $tableBackgrounds;
	var $inlineDisplayOff;
	var $kt_y00;
	var $kt_p00;
	var $upperCase;
	var $checkSIP;
	var $checkSMP;
	var $checkCJK;

	var $watermarkImgAlpha;
	var $PDFAXwarnings;

	var $MetadataRoot;
	var $OutputIntentRoot;
	var $InfoRoot;
	var $associatedFilesRoot;

	var $current_filename;
	var $parsers;
	var $current_parser;
	var $_obj_stack;
	var $_don_obj_stack;
	var $_current_obj_id;
	var $tpls;
	var $tpl;
	var $tplprefix;
	var $_res;

	var $pdf_version;

	protected $fontDir;

	var $tempDir;

	var $allowAnnotationFiles;

	var $fontdata;

	var $noImageFile;
	var $lastblockbottommargin;
	var $baselineC;

	// mPDF 5.7.3  inline text-decoration parameters
	var $baselineSup;
	var $baselineSub;
	var $baselineS;
	var $baselineO;

	var $subPos;
	var $subArrMB;
	var $ReqFontStyle;
	var $tableClipPath;

	var $fullImageHeight;

	var $inFixedPosBlock;  // Internal flag for position:fixed block
	var $fixedPosBlock;  // Buffer string for position:fixed block
	var $fixedPosBlockDepth;
	var $fixedPosBlockBBox;
	var $fixedPosBlockSave;
	var $maxPosL;
	var $maxPosR;
	var $loaded;

	var $extraFontSubsets;

	var $docTemplateStart;  // Internal flag for page (page no. -1) that docTemplate starts on

	var $time0;

	var $hyphenationDictionaryFile;

	var $spanbgcolorarray;
	var $default_font;
	var $headerbuffer;
	var $lastblocklevelchange;
	var $nestedtablejustfinished;
	var $linebreakjustfinished;
	var $cell_border_dominance_L;
	var $cell_border_dominance_R;
	var $cell_border_dominance_T;
	var $cell_border_dominance_B;
	var $table_keep_together;
	var $plainCell_properties;
	var $shrin_k1;
	var $outerfilled;

	var $blockContext;
	var $floatDivs;

	var $patterns;
	var $pageBackgrounds;

	var $bodyBackgroundGradient;
	var $bodyBackgroundImage;
	var $bodyBackgroundColor;

	var $writingHTMLheader; // internal flag - used both for writing HTMLHeaders/Footers and FixedPos block
	var $writingHTMLfooter;

	var $angle;

	var $gradients;

	var $kwt_Reference;
	var $kwt_BMoutlines;
	var $kwt_toc;

	var $tbrot_BMoutlines;
	var $tbrot_toc;

	var $col_BMoutlines;
	var $col_toc;

	var $floatbuffer;
	var $floatmargins;

	var $bullet;
	var $bulletarray;

	var $currentLang;
	var $default_lang;

	var $default_available_fonts;

	var $pageTemplate;
	var $docTemplate;
	var $docTemplateContinue;

	var $arabGlyphs;
	var $arabHex;
	var $persianGlyphs;
	var $persianHex;
	var $arabVowels;
	var $arabPrevLink;
	var $arabNextLink;

	var $formobjects; // array of Form Objects for WMF
	var $InlineProperties;
	var $InlineAnnots;
	var $InlineBDF; // mPDF 6 Bidirectional formatting
	var $InlineBDFctr; // mPDF 6

	var $ktAnnots;
	var $tbrot_Annots;
	var $kwt_Annots;
	var $columnAnnots;
	var $columnForms;
	var $tbrotForms;

	var $PageAnnots;

	var $pageDim; // Keep track of page wxh for orientation changes - set in _beginpage, used in _putannots

	var $breakpoints;

	var $tableLevel;
	var $tbctr;
	var $innermostTableLevel;
	var $saveTableCounter;
	var $cellBorderBuffer;

	var $saveHTMLFooter_height;
	var $saveHTMLFooterE_height;

	var $firstPageBoxHeader;
	var $firstPageBoxHeaderEven;
	var $firstPageBoxFooter;
	var $firstPageBoxFooterEven;

	var $page_box;

	var $show_marks; // crop or cross marks
	var $basepathIsLocal;

	var $use_kwt;
	var $kwt;
	var $kwt_height;
	var $kwt_y0;
	var $kwt_x0;
	var $kwt_buffer;
	var $kwt_Links;
	var $kwt_moved;
	var $kwt_saved;

	var $PageNumSubstitutions;

	var $table_borders_separate;
	var $base_table_properties;
	var $borderstyles;

	var $blockjustfinished;

	var $orig_bMargin;
	var $orig_tMargin;
	var $orig_lMargin;
	var $orig_rMargin;
	var $orig_hMargin;
	var $orig_fMargin;

	var $pageHTMLheaders;
	var $pageHTMLfooters;

	var $saveHTMLHeader;
	var $saveHTMLFooter;

	var $HTMLheaderPageLinks;
	var $HTMLheaderPageAnnots;
	var $HTMLheaderPageForms;

	// See Config\FontVariables for these next 5 values
	var $available_unifonts;
	var $sans_fonts;
	var $serif_fonts;
	var $mono_fonts;
	var $defaultSubsFont;

	// List of ALL available CJK fonts (incl. styles) (Adobe add-ons)  hw removed
	var $available_CJK_fonts;

	var $HTMLHeader;
	var $HTMLFooter;
	var $HTMLHeaderE;
	var $HTMLFooterE;
	var $bufferoutput;

	// CJK fonts
	var $Big5_widths;
	var $GB_widths;
	var $SJIS_widths;
	var $UHC_widths;

	// SetProtection
	var $encrypted;

	var $enc_obj_id; // encryption object id

	// Bookmark
	var $BMoutlines;
	var $OutlineRoot;

	// INDEX
	var $ColActive;
	var $Reference;
	var $CurrCol;
	var $NbCol;
	var $y0;   // Top ordinate of columns

	var $ColL;
	var $ColWidth;
	var $ColGap;

	// COLUMNS
	var $ColR;
	var $ChangeColumn;
	var $columnbuffer;
	var $ColDetails;
	var $columnLinks;
	var $colvAlign;

	// Substitutions
	var $substitute;  // Array of substitution strings e.g. <ttz>112</ttz>
	var $entsearch;  // Array of HTML entities (>ASCII 127) to substitute
	var $entsubstitute; // Array of substitution decimal unicode for the Hi entities

	// Default values if no style sheet offered	(cf. http://www.w3.org/TR/CSS21/sample.html)
	var $defaultCSS;
	var $defaultCssFile;

	var $lastoptionaltag; // Save current block item which HTML specifies optionsl endtag
	var $pageoutput;
	var $charset_in;
	var $blk;
	var $blklvl;
	var $ColumnAdjust;

	var $ws; // Word spacing

	var $HREF;
	var $pgwidth;
	var $fontlist;
	var $oldx;
	var $oldy;
	var $B;
	var $I;

	var $tdbegin;
	var $table;
	var $cell;
	var $col;
	var $row;

	var $divbegin;
	var $divwidth;
	var $divheight;
	var $spanbgcolor;

	// mPDF 6 Used for table cell (block-type) properties
	var $cellTextAlign;
	var $cellLineHeight;
	var $cellLineStackingStrategy;
	var $cellLineStackingShift;

	// mPDF 6  Lists
	var $listcounter;
	var $listlvl;
	var $listtype;
	var $listitem;

	var $pjustfinished;
	var $ignorefollowingspaces;
	var $SMALL;
	var $BIG;
	var $dash_on;
	var $dotted_on;

	var $textbuffer;
	var $currentfontstyle;
	var $currentfontfamily;
	var $currentfontsize;
	var $colorarray;
	var $bgcolorarray;
	var $internallink;
	var $enabledtags;

	var $lineheight;
	var $basepath;
	var $textparam;

	var $specialcontent;
	var $selectoption;
	var $objectbuffer;

	// Table Rotation
	var $table_rotate;
	var $tbrot_maxw;
	var $tbrot_maxh;
	var $tablebuffer;
	var $tbrot_align;
	var $tbrot_Links;

	var $keep_block_together; // Keep a Block from page-break-inside: avoid

	var $tbrot_y0;
	var $tbrot_x0;
	var $tbrot_w;
	var $tbrot_h;

	var $mb_enc;
	var $originalMbEnc;
	var $originalMbRegexEnc;

	var $directionality;

	var $extgstates; // Used for alpha channel - Transparency (Watermark)
	var $mgl;
	var $mgt;
	var $mgr;
	var $mgb;

	var $tts;
	var $ttz;
	var $tta;

	// Best to alter the below variables using default stylesheet above
	var $page_break_after_avoid;
	var $margin_bottom_collapse;
	var $default_font_size; // in pts
	var $original_default_font_size; // used to save default sizes when using table default
	var $original_default_font;
	var $watermark_font;
	var $defaultAlign;

	// TABLE
	var $defaultTableAlign;
	var $tablethead;
	var $thead_font_weight;
	var $thead_font_style;
	var $thead_font_smCaps;
	var $thead_valign_default;
	var $thead_textalign_default;
	var $tabletfoot;
	var $tfoot_font_weight;
	var $tfoot_font_style;
	var $tfoot_font_smCaps;
	var $tfoot_valign_default;
	var $tfoot_textalign_default;

	var $trow_text_rotate;

	var $cellPaddingL;
	var $cellPaddingR;
	var $cellPaddingT;
	var $cellPaddingB;
	var $table_border_attr_set;
	var $table_border_css_set;

	var $shrin_k; // factor with which to shrink tables - used internally - do not change
	var $shrink_this_table_to_fit; // 0 or false to disable; value (if set) gives maximum factor to reduce fontsize
	var $MarginCorrection; // corrects for OddEven Margins
	var $margin_footer;
	var $margin_header;

	var $tabletheadjustfinished;
	var $usingCoreFont;
	var $charspacing;

	var $js;

	/**
	 * Set timeout for cURL
	 *
	 * @var int
	 */
	var $curlTimeout;

	/**
	 * Set to true to follow redirects with cURL.
	 *
	 * @var bool
	 */
	var $curlFollowLocation;

	/**
	 * Set to true to allow unsafe SSL HTTPS requests.
	 *
	 * Can be useful when using CDN with HTTPS and if you don't want to configure settings with SSL certificates.
	 *
	 * @var bool
	 */
	var $curlAllowUnsafeSslRequests;

	// Private properties FROM FPDF
	var $DisplayPreferences;
	var $flowingBlockAttr;

	var $page; // current page number

	var $n; // current object number
	var $n_js; // current object number

	var $n_ocg_hidden;
	var $n_ocg_print;
	var $n_ocg_view;

	var $offsets; // array of object offsets
	var $buffer; // buffer holding in-memory PDF
	var $pages; // array containing pages
	var $state; // current document state
	var $compress; // compression flag

	var $DefOrientation; // default orientation
	var $CurOrientation; // current orientation
	var $OrientationChanges; // array indicating orientation changes

	var $k; // scale factor (number of points in user unit)

	var $fwPt;
	var $fhPt; // dimensions of page format in points
	var $fw;
	var $fh; // dimensions of page format in user unit
	var $wPt;
	var $hPt; // current dimensions of page in points

	var $w;
	var $h; // current dimensions of page in user unit

	var $lMargin; // left margin
	var $tMargin; // top margin
	var $rMargin; // right margin
	var $bMargin; // page break margin
	var $cMarginL; // cell margin Left
	var $cMarginR; // cell margin Right
	var $cMarginT; // cell margin Left
	var $cMarginB; // cell margin Right

	var $DeflMargin; // Default left margin
	var $DefrMargin; // Default right margin

	var $x;
	var $y; // current position in user unit for cell positioning

	var $lasth; // height of last cell printed
	var $LineWidth; // line width in user unit

	var $CoreFonts; // array of standard font names
	var $fonts; // array of used fonts
	var $FontFiles; // array of font files

	var $images; // array of used images
	var $imageVars = []; // array of image vars

	var $PageLinks; // array of links in pages
	var $links; // array of internal links
	var $FontFamily; // current font family
	var $FontStyle; // current font style
	var $CurrentFont; // current font info
	var $FontSizePt; // current font size in points
	var $FontSize; // current font size in user unit
	var $DrawColor; // commands for drawing color
	var $FillColor; // commands for filling color
	var $TextColor; // commands for text color
	var $ColorFlag; // indicates whether fill and text colors are different
	var $autoPageBreak; // automatic page breaking
	var $PageBreakTrigger; // threshold used to trigger page breaks
	var $InFooter; // flag set when processing footer

	var $InHTMLFooter;
	var $processingFooter; // flag set when processing footer - added for columns
	var $processingHeader; // flag set when processing header - added for columns
	var $ZoomMode; // zoom display mode
	var $LayoutMode; // layout display mode
	var $title; // title
	var $subject; // subject
	var $author; // author
	var $keywords; // keywords
	var $creator; // creator

	var $customProperties; // array of custom document properties

	var $associatedFiles; // associated files (see SetAssociatedFiles below)
	var $additionalXmpRdf; // additional rdf added in xmp

	var $aliasNbPg; // alias for total number of pages
	var $aliasNbPgGp; // alias for total number of pages in page group

	var $ispre;
	var $outerblocktags;
	var $innerblocktags;

	/**
	 * @var string
	 */
	protected $fontDescriptor;

	/**
	 * @var \Mpdf\Otl
	 */
	protected $otl;

	/**
	 * @var \Mpdf\CssManager
	 */
	protected $cssManager;

	/**
	 * @var \Mpdf\Gradient
	 */
	protected $gradient;

	/**
	 * @var \Mpdf\Image\Bmp
	 */
	protected $bmp;

	/**
	 * @var \Mpdf\Image\Wmf
	 */
	protected $wmf;

	/**
	 * @var \Mpdf\TableOfContents
	 */
	protected $tableOfContents;

	/**
	 * @var \Mpdf\Form
	 */
	protected $form;

	/**
	 * @var \Mpdf\DirectWrite
	 */
	protected $directWrite;

	/**
	 * @var \Mpdf\Cache
	 */
	protected $cache;

	/**
	 * @var \Mpdf\Fonts\FontCache
	 */
	protected $fontCache;

	/**
	 * @var \Mpdf\Fonts\FontFileFinder
	 */
	protected $fontFileFinder;

	/**
	 * @var \Mpdf\Tag
	 */
	protected $tag;

	/**
	 * @var \Mpdf\Barcode
	 * @todo solve Tag dependency and make private
	 */
	public $barcode;

	/**
	 * @var \Mpdf\QrCode\QrCode
	 */
	protected $qrcode;

	/**
	 * @var \Mpdf\SizeConverter
	 */
	protected $sizeConverter;

	/**
	 * @var \Mpdf\Color\ColorConverter
	 */
	protected $colorConverter;

	/**
	 * @var \Mpdf\Color\ColorModeConverter
	 */
	protected $colorModeConverter;

	/**
	 * @var \Mpdf\Color\ColorSpaceRestrictor
	 */
	protected $colorSpaceRestrictor;

	/**
	 * @var \Mpdf\Hyphenator
	 */
	protected $hyphenator;

	/**
	 * @var \Mpdf\Pdf\Protection
	 */
	protected $protection;

	/**
	 * @var \Mpdf\Image\ImageProcessor
	 */
	protected $imageProcessor;

	/**
	 * @var \Mpdf\Language\LanguageToFontInterface
	 */
	protected $languageToFont;

	/**
	 * @var \Mpdf\Language\ScriptToLanguageInterface
	 */
	protected $scriptToLanguage;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var string[]
	 */
	protected $services;

    abstract function AcceptPageBreak();
    abstract function AddPage(		$orientation = '',
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
		$newformat = '');
    abstract public function border_details($bd);
    abstract public function Circle($x, $y, $r, $style = 'S');
    abstract public function DisableTags($str = '');
    abstract public function DivLn($h, $level = -3, $move_y = true, $collapsible = false, $state = 0);
    abstract public function docPageNum($num = 0, $extras = false);
    abstract public function docPageNumTotal($num = 0, $extras = false);
    abstract public function EndLayer();
    abstract public function GetFirstBlockFill();
    abstract public function GetStringWidth($s, $addSubset = true, $OTLdata = false, $textvar = 0, $includeKashida = false);
    abstract public function issetBorder($var, $flag);
    abstract public function Line($x1, $y1, $x2, $y2);
    abstract public function magic_reverse_dir(&$chunk, $dir, &$chunkOTLdata);
    abstract public function pdf_write_value(&$value);
    abstract public function printbuffer($arrayaux, $blockstate = 0, $is_table = false, $table_draft = false, $cell_dir = '');
    abstract public function printcellbuffer();
    abstract public function printfloatbuffer();
    abstract public function printkwtbuffer();
    abstract public function PrintPageBackgrounds($adjustmenty = 0);
    abstract public function PrintTableBackgrounds($adjustmenty = 0);
    abstract public function Rect($x, $y, $w, $h, $style = '');
    abstract public function Reset();
    abstract public function ResetMargins();
    abstract public function Rotate($angle, $x = -1, $y = -1);
    abstract public function SetAlpha($alpha, $bm = 'Normal', $return = false, $mode = 'B');
    abstract public function SetAutoPageBreak($auto, $margin = 0);
    abstract public function SetBackground(&$properties, &$maxwidth);
    abstract public function SetBasePath($str = '');
    abstract public function setBorder(&$var, $flag, $set = true);
    abstract public function SetCompression($compress);
    abstract public function SetDash($black = false, $white = false);
    abstract public function SetDColor($col, $return = false);
    abstract public function SetDefaultFont($font);
    abstract public function SetDefaultFontSize($fontsize);
    abstract public function SetDisplayMode($zoom, $layout = 'continuous');
    abstract public function SetDisplayPreferences($preferences);
    abstract public function SetFColor($col, $return = false);
    abstract public function SetFont($family, $style = '', $size = 0, $write = true, $forcewrite = false);
    abstract public function setHiEntitySubstitutions();
    abstract public function SetHTMLFooter($footer = '', $OE = '');
    abstract public function SetHTMLHeader($header = '', $OE = '', $write = false);
    abstract public function SetLineCap($mode = 2);
    abstract public function SetLineHeight($FontPt = '', $lh = '');
    abstract public function SetLineJoin($mode = 0);
    abstract public function SetLineWidth($width);
    abstract public function SetMargins($left, $right, $top);
    abstract public function setMBencoding($enc);
    abstract public function SetPagedMediaCSS($name, $first, $oddEven);
    abstract public function SetSubstitutions();
    abstract public function SetTColor($col, $return = false);
    abstract public function SetVisibility($v);
    abstract public function StartTransform($returnstring = false);
    abstract public function StopTransform($returnstring = false);
    abstract public function TableCheckMinWidth($maxwidth, $forcewrap = 0, $textbuffer = [], $checkletter = false);
    abstract public function TableHeaderFooter($content = '', $tablestartpage = '', $tablestartcolumn = '', $horf = 'H', $level = 0, $firstSpread = true, $finalSpread = true);
    abstract public function Text($x, $y, $txt, $OTLdata = [], $textvar = 0, $aixextra = '', $coordsys = '', $return = false);
    abstract public function toFloat($num);
    abstract public function transformTranslate($t_x, $t_y, $returnstring = false);
    abstract public function UTF8StringToArray($str, $addSubset = true);
    abstract public function UTF8ToUTF16BE($str, $setbom = true);
    abstract public function WriteHTML($html, $sub = 0, $init = true, $close = true);


	/**
	 * @param mixed[] $config
	 */
	public function __construct(array $config = [])
	{
		$this->_dochecks();

		list(
			$mode,
			$format,
			$default_font_size,
			$default_font,
			$mgl,
			$mgr,
			$mgt,
			$mgb,
			$mgh,
			$mgf,
			$orientation
		) = $this->initConstructorParams($config);

		$this->logger = new NullLogger();

		$originalConfig = $config;
		$config = $this->initConfig($originalConfig);

		$this->sizeConverter = new SizeConverter($this->dpi, $this->default_font_size, $this->logger);

		$this->colorModeConverter = new ColorModeConverter();
		$this->colorSpaceRestrictor = new ColorSpaceRestrictor(
			$this,
			$this->colorModeConverter,
			$this->restrictColorSpace
		);
		$this->colorConverter = new ColorConverter($this, $this->colorModeConverter, $this->colorSpaceRestrictor);


		$this->gradient = new Gradient($this, $this->sizeConverter, $this->colorConverter);
		$this->tableOfContents = new TableOfContents($this, $this->sizeConverter);

		$this->cache = new Cache($config['tempDir']);
		$this->fontCache = new FontCache(new Cache($config['tempDir'] . '/ttfontdata'));

		$this->fontFileFinder = new FontFileFinder($config['fontDir']);

		$this->cssManager = new CssManager($this, $this->cache, $this->sizeConverter, $this->colorConverter);

		$this->otl = new Otl($this, $this->fontCache);

		$this->form = new Form($this, $this->otl, $this->colorConverter);

		$this->hyphenator = new Hyphenator($this);

		$this->imageProcessor = new ImageProcessor(
			$this,
			$this->otl,
			$this->cssManager,
			$this->sizeConverter,
			$this->colorConverter,
			$this->colorModeConverter,
			$this->cache,
			$this->languageToFont,
			$this->scriptToLanguage,
			$this->logger
		);

		$this->tag = new Tag(
			$this,
			$this->cache,
			$this->cssManager,
			$this->form,
			$this->otl,
			$this->tableOfContents,
			$this->sizeConverter,
			$this->colorConverter,
			$this->imageProcessor,
			$this->languageToFont
		);

		$this->services = [
			'otl',
			'bmp',
			'cache',
			'cssManager',
			'directWrite',
			'fontCache',
			'fontFileFinder',
			'form',
			'gradient',
			'tableOfContents',
			'tag',
			'wmf',
			'sizeConverter',
			'colorConverter',
			'hyphenator',
			'imageProcessor',
			'protection',
			'languageToFont',
			'scriptToLanguage',
		];

		$this->time0 = microtime(true);

		$this->writingToC = false;

		$this->layers = [];
		$this->current_layer = 0;
		$this->open_layer_pane = false;

		$this->visibility = 'visible';

		$this->tableBackgrounds = [];
		$this->uniqstr = '20110230'; // mPDF 5.7.2
		$this->kt_y00 = '';
		$this->kt_p00 = '';
		$this->BMPonly = [];
		$this->page = 0;
		$this->n = 2;
		$this->buffer = '';
		$this->objectbuffer = [];
		$this->pages = [];
		$this->OrientationChanges = [];
		$this->state = 0;
		$this->fonts = [];
		$this->FontFiles = [];
		$this->images = [];
		$this->links = [];
		$this->InFooter = false;
		$this->processingFooter = false;
		$this->processingHeader = false;
		$this->lasth = 0;
		$this->FontFamily = '';
		$this->FontStyle = '';
		$this->FontSizePt = 9;

		// Small Caps
		$this->inMeter = false;
		$this->decimal_offset = 0;

		$this->PDFAXwarnings = [];

		$this->defTextColor = $this->TextColor = $this->SetTColor($this->colorConverter->convert(0, $this->PDFAXwarnings), true);
		$this->defDrawColor = $this->DrawColor = $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings), true);
		$this->defFillColor = $this->FillColor = $this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings), true);

		$this->upperCase = require __DIR__ . '/../data/upperCase.php';

		$this->extrapagebreak = true; // mPDF 6 pagebreaktype

		$this->ColorFlag = false;
		$this->extgstates = [];

		$this->mb_enc = 'windows-1252';
		$this->originalMbEnc = mb_internal_encoding();
		$this->originalMbRegexEnc = mb_regex_encoding();

		$this->directionality = 'ltr';
		$this->defaultAlign = 'L';
		$this->defaultTableAlign = 'L';

		$this->fixedPosBlockSave = [];
		$this->extraFontSubsets = 0;

		$this->blockContext = 1;
		$this->floatDivs = [];
		$this->DisplayPreferences = '';

		// Tiling patterns used for backgrounds
		$this->patterns = [];
		$this->pageBackgrounds = [];
		$this->gradients = [];

		// internal flag - used both for writing HTMLHeaders/Footers and FixedPos block
		$this->writingHTMLheader = false;
		// internal flag - used both for writing HTMLHeaders/Footers and FixedPos block
		$this->writingHTMLfooter = false;

		$this->kwt_Reference = [];
		$this->kwt_BMoutlines = [];
		$this->kwt_toc = [];

		$this->tbrot_BMoutlines = [];
		$this->tbrot_toc = [];

		$this->col_BMoutlines = [];
		$this->col_toc = [];

		$this->pgsIns = [];
		$this->PDFAXwarnings = [];
		$this->inlineDisplayOff = false;
		$this->lSpacingCSS = '';
		$this->wSpacingCSS = '';
		$this->fixedlSpacing = false;
		$this->minwSpacing = 0;

		// Baseline for text
		$this->baselineC = 0.35;

		// mPDF 5.7.3  inline text-decoration parameters
		// Sets default change in baseline for <sup> text as factor of preceeding fontsize
		// 0.35 has been recommended; 0.5 matches applications like MS Word
		$this->baselineSup = 0.5;

		// Sets default change in baseline for <sub> text as factor of preceeding fontsize
		$this->baselineSub = -0.2;
		// Sets default height for <strike> text as factor of fontsize
		$this->baselineS = 0.3;
		// Sets default height for overline text as factor of fontsize
		$this->baselineO = 1.1;

		$this->noImageFile = __DIR__ . '/../data/no_image.jpg';
		$this->subPos = 0;

		$this->fullImageHeight = false;
		$this->floatbuffer = [];
		$this->floatmargins = [];
		$this->formobjects = []; // array of Form Objects for WMF
		$this->InlineProperties = [];
		$this->InlineAnnots = [];
		$this->InlineBDF = []; // mPDF 6
		$this->InlineBDFctr = 0; // mPDF 6
		$this->tbrot_Annots = [];
		$this->kwt_Annots = [];
		$this->columnAnnots = [];
		$this->PageLinks = [];
		$this->OrientationChanges = [];
		$this->pageDim = [];
		$this->saveHTMLHeader = [];
		$this->saveHTMLFooter = [];
		$this->PageAnnots = [];
		$this->PageNumSubstitutions = [];
		$this->breakpoints = []; // used in columnbuffer
		$this->tableLevel = 0;
		$this->tbctr = []; // counter for nested tables at each level
		$this->page_box = [];
		$this->show_marks = ''; // crop or cross marks
		$this->kwt = false;
		$this->kwt_height = 0;
		$this->kwt_y0 = 0;
		$this->kwt_x0 = 0;
		$this->kwt_buffer = [];
		$this->kwt_Links = [];
		$this->kwt_moved = false;
		$this->kwt_saved = false;
		$this->PageNumSubstitutions = [];
		$this->base_table_properties = [];
		$this->borderstyles = ['inset', 'groove', 'outset', 'ridge', 'dotted', 'dashed', 'solid', 'double'];
		$this->tbrot_align = 'C';

		$this->pageHTMLheaders = [];
		$this->pageHTMLfooters = [];
		$this->HTMLheaderPageLinks = [];
		$this->HTMLheaderPageAnnots = [];

		$this->HTMLheaderPageForms = [];
		$this->columnForms = [];
		$this->tbrotForms = [];

		$this->pageoutput = [];

		$this->bufferoutput = false;

		$this->encrypted = false;

		$this->BMoutlines = [];
		$this->ColActive = 0;          // Flag indicating that columns are on (the index is being processed)
		$this->Reference = [];    // Array containing the references
		$this->CurrCol = 0;               // Current column number
		$this->ColL = [0];   // Array of Left pos of columns - absolute - needs Margin correction for Odd-Even
		$this->ColR = [0];   // Array of Right pos of columns - absolute pos - needs Margin correction for Odd-Even
		$this->ChangeColumn = 0;
		$this->columnbuffer = [];
		$this->ColDetails = [];  // Keeps track of some column details
		$this->columnLinks = [];  // Cross references PageLinks
		$this->substitute = [];  // Array of substitution strings e.g. <ttz>112</ttz>
		$this->entsearch = [];  // Array of HTML entities (>ASCII 127) to substitute
		$this->entsubstitute = []; // Array of substitution decimal unicode for the Hi entities
		$this->lastoptionaltag = '';
		$this->charset_in = '';
		$this->blk = [];
		$this->blklvl = 0;
		$this->tts = false;
		$this->ttz = false;
		$this->tta = false;
		$this->ispre = false;

		$this->checkSIP = false;
		$this->checkSMP = false;
		$this->checkCJK = false;

		$this->page_break_after_avoid = false;
		$this->margin_bottom_collapse = false;
		$this->tablethead = 0;
		$this->tabletfoot = 0;
		$this->table_border_attr_set = 0;
		$this->table_border_css_set = 0;
		$this->shrin_k = 1.0;
		$this->shrink_this_table_to_fit = 0;
		$this->MarginCorrection = 0;

		$this->tabletheadjustfinished = false;
		$this->usingCoreFont = false;
		$this->charspacing = 0;

		$this->autoPageBreak = true;

		$this->_setPageSize($format, $orientation);
		$this->DefOrientation = $orientation;

		$this->margin_header = $mgh;
		$this->margin_footer = $mgf;

		$bmargin = $mgb;

		$this->DeflMargin = $mgl;
		$this->DefrMargin = $mgr;

		$this->orig_tMargin = $mgt;
		$this->orig_bMargin = $bmargin;
		$this->orig_lMargin = $this->DeflMargin;
		$this->orig_rMargin = $this->DefrMargin;
		$this->orig_hMargin = $this->margin_header;
		$this->orig_fMargin = $this->margin_footer;

		if ($this->setAutoTopMargin == 'pad') {
			$mgt += $this->margin_header;
		}
		if ($this->setAutoBottomMargin == 'pad') {
			$mgb += $this->margin_footer;
		}

		// sets l r t margin
		$this->SetMargins($this->DeflMargin, $this->DefrMargin, $mgt);

		// Automatic page break
		// sets $this->bMargin & PageBreakTrigger
		$this->SetAutoPageBreak($this->autoPageBreak, $bmargin);

		$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;

		// Interior cell margin (1 mm) ? not used
		$this->cMarginL = 1;
		$this->cMarginR = 1;

		// Line width (0.2 mm)
		$this->LineWidth = .567 / Mpdf::SCALE;

		// Enable all tags as default
		$this->DisableTags();
		// Full width display mode
		$this->SetDisplayMode(100); // fullwidth? 'fullpage'

		// Compression
		$this->SetCompression(true);
		// Set default display preferences
		$this->SetDisplayPreferences('');

		$this->initFontConfig($originalConfig);

		// Available fonts
		$this->available_unifonts = [];
		foreach ($this->fontdata as $f => $fs) {
			if (isset($fs['R']) && $fs['R']) {
				$this->available_unifonts[] = $f;
			}
			if (isset($fs['B']) && $fs['B']) {
				$this->available_unifonts[] = $f . 'B';
			}
			if (isset($fs['I']) && $fs['I']) {
				$this->available_unifonts[] = $f . 'I';
			}
			if (isset($fs['BI']) && $fs['BI']) {
				$this->available_unifonts[] = $f . 'BI';
			}
		}

		$this->default_available_fonts = $this->available_unifonts;

		$optcore = false;
		$onlyCoreFonts = false;
		if (preg_match('/([\-+])aCJK/i', $mode, $m)) {
			$mode = preg_replace('/([\-+])aCJK/i', '', $mode); // mPDF 6
			if ($m[1] == '+') {
				$this->useAdobeCJK = true;
			} else {
				$this->useAdobeCJK = false;
			}
		}

		if (strlen($mode) == 1) {
			if ($mode == 's') {
				$this->percentSubset = 100;
				$mode = '';
			} elseif ($mode == 'c') {
				$onlyCoreFonts = true;
				$mode = '';
			}
		} elseif (substr($mode, -2) == '-s') {
			$this->percentSubset = 100;
			$mode = substr($mode, 0, strlen($mode) - 2);
		} elseif (substr($mode, -2) == '-c') {
			$onlyCoreFonts = true;
			$mode = substr($mode, 0, strlen($mode) - 2);
		} elseif (substr($mode, -2) == '-x') {
			$optcore = true;
			$mode = substr($mode, 0, strlen($mode) - 2);
		}

		// Autodetect if mode is a language_country string (en-GB or en_GB or en)
		if ($mode && $mode != 'UTF-8') { // mPDF 6
			list ($coreSuitable, $mpdf_pdf_unifont) = $this->languageToFont->getLanguageOptions($mode, $this->useAdobeCJK);
			if ($coreSuitable && $optcore) {
				$onlyCoreFonts = true;
			}
			if ($mpdf_pdf_unifont) {  // mPDF 6
				$default_font = $mpdf_pdf_unifont;
			}
			$this->currentLang = $mode;
			$this->default_lang = $mode;
		}

		$this->onlyCoreFonts = $onlyCoreFonts;

		if ($this->onlyCoreFonts) {
			$this->setMBencoding('windows-1252'); // sets $this->mb_enc
		} else {
			$this->setMBencoding('UTF-8'); // sets $this->mb_enc
		}
		@mb_regex_encoding('UTF-8'); // required only for mb_ereg... and mb_split functions

		// Adobe CJK fonts
		$this->available_CJK_fonts = [
			'gb',
			'big5',
			'sjis',
			'uhc',
			'gbB',
			'big5B',
			'sjisB',
			'uhcB',
			'gbI',
			'big5I',
			'sjisI',
			'uhcI',
			'gbBI',
			'big5BI',
			'sjisBI',
			'uhcBI',
		];

		// Standard fonts
		$this->CoreFonts = [
			'ccourier' => 'Courier',
			'ccourierB' => 'Courier-Bold',
			'ccourierI' => 'Courier-Oblique',
			'ccourierBI' => 'Courier-BoldOblique',
			'chelvetica' => 'Helvetica',
			'chelveticaB' => 'Helvetica-Bold',
			'chelveticaI' => 'Helvetica-Oblique',
			'chelveticaBI' => 'Helvetica-BoldOblique',
			'ctimes' => 'Times-Roman',
			'ctimesB' => 'Times-Bold',
			'ctimesI' => 'Times-Italic',
			'ctimesBI' => 'Times-BoldItalic',
			'csymbol' => 'Symbol',
			'czapfdingbats' => 'ZapfDingbats'
		];

		$this->fontlist = [
			"ctimes",
			"ccourier",
			"chelvetica",
			"csymbol",
			"czapfdingbats"
		];

		// Substitutions
		$this->setHiEntitySubstitutions();

		if ($this->onlyCoreFonts) {
			$this->useSubstitutions = true;
			$this->SetSubstitutions();
		} else {
			$this->useSubstitutions = $config['useSubstitutions'];
		}

		if (file_exists($this->defaultCssFile)) {
			$css = file_get_contents($this->defaultCssFile);
			$this->cssManager->ReadCSS('<style> ' . $css . ' </style>');
		} else {
			throw new \Mpdf\MpdfException(sprintf('Unable to read default CSS file "%s"', $this->defaultCssFile));
		}

		if ($default_font == '') {
			if ($this->onlyCoreFonts) {
				if (in_array(strtolower($this->defaultCSS['BODY']['FONT-FAMILY']), $this->mono_fonts)) {
					$default_font = 'ccourier';
				} elseif (in_array(strtolower($this->defaultCSS['BODY']['FONT-FAMILY']), $this->sans_fonts)) {
					$default_font = 'chelvetica';
				} else {
					$default_font = 'ctimes';
				}
			} else {
				$default_font = $this->defaultCSS['BODY']['FONT-FAMILY'];
			}
		}
		if (!$default_font_size) {
			$mmsize = $this->sizeConverter->convert($this->defaultCSS['BODY']['FONT-SIZE']);
			$default_font_size = $mmsize * (Mpdf::SCALE);
		}

		if ($default_font) {
			$this->SetDefaultFont($default_font);
		}
		if ($default_font_size) {
			$this->SetDefaultFontSize($default_font_size);
		}

		$this->SetLineHeight(); // lineheight is in mm

		$this->SetFColor($this->colorConverter->convert(255, $this->PDFAXwarnings));
		$this->HREF = '';
		$this->oldy = -1;
		$this->B = 0;
		$this->I = 0;

		// mPDF 6  Lists
		$this->listlvl = 0;
		$this->listtype = [];
		$this->listitem = [];
		$this->listcounter = [];

		$this->tdbegin = false;
		$this->table = [];
		$this->cell = [];
		$this->col = -1;
		$this->row = -1;
		$this->cellBorderBuffer = [];

		$this->divbegin = false;
		// mPDF 6
		$this->cellTextAlign = '';
		$this->cellLineHeight = '';
		$this->cellLineStackingStrategy = '';
		$this->cellLineStackingShift = '';

		$this->divwidth = 0;
		$this->divheight = 0;
		$this->spanbgcolor = false;
		$this->spanborder = false;
		$this->spanborddet = [];

		$this->blockjustfinished = false;
		$this->ignorefollowingspaces = true; // in order to eliminate exceeding left-side spaces
		$this->dash_on = false;
		$this->dotted_on = false;
		$this->textshadow = '';

		$this->currentfontfamily = '';
		$this->currentfontsize = '';
		$this->currentfontstyle = '';
		$this->colorarray = ''; // mPDF 6
		$this->spanbgcolorarray = ''; // mPDF 6
		$this->textbuffer = [];
		$this->internallink = [];
		$this->basepath = "";

		$this->SetBasePath('');

		$this->textparam = [];

		$this->specialcontent = '';
		$this->selectoption = [];

		/* -- IMPORTS -- */
		$this->parsers = [];
		$this->tpls = [];
		$this->tpl = 0;
		$this->tplprefix = "/TPL";
		/* -- END IMPORTS -- */
	}

	protected function initConfig(array $config)
	{
		$configObject = new ConfigVariables();
		$defaults = $configObject->getDefaults();
		$config = array_intersect_key($config + $defaults, $defaults);

		foreach ($config as $var => $val) {
			$this->{$var} = $val;
		}

		return $config;
	}

	protected function initConstructorParams(array $config)
	{
		$constructor = [
			'mode' => '',
			'format' => 'A4',
			'default_font_size' => 0,
			'default_font' => '',
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 16,
			'margin_bottom' => 16,
			'margin_header' => 9,
			'margin_footer' => 9,
			'orientation' => 'P',
		];

		foreach ($constructor as $key => $val) {
			if (isset($config[$key])) {
				$constructor[$key] = $config[$key];
			}
		}

		return array_values($constructor);
	}

	protected function initFontConfig(array $config)
	{
		$configObject = new FontVariables();
		$defaults = $configObject->getDefaults();
		$config = array_intersect_key($config + $defaults, $defaults);
		foreach ($config as $var => $val) {
			$this->{$var} = $val;
		}

		return $config;
	}

	function _setPageSize($format, &$orientation)
	{
		if (is_string($format)) {

			if (empty($format)) {
				$format = 'A4';
			}

			// e.g. A4-L = A4 landscape, A4-P = A4 portrait
			if (preg_match('/([0-9a-zA-Z]*)-([P,L])/i', $format, $m)) {
				$format = $m[1];
				$orientation = $m[2];
			} elseif (empty($orientation)) {
				$orientation = 'P';
			}

			$format = PageFormat::getSizeFromName($format);

			$this->fwPt = $format[0];
			$this->fhPt = $format[1];

		} else {

			if (!$format[0] || !$format[1]) {
				throw new \Mpdf\MpdfException('Invalid page format: ' . $format[0] . ' ' . $format[1]);
			}

			$this->fwPt = $format[0] * Mpdf::SCALE;
			$this->fhPt = $format[1] * Mpdf::SCALE;
		}

		$this->fw = $this->fwPt / Mpdf::SCALE;
		$this->fh = $this->fhPt / Mpdf::SCALE;

		// Page orientation
		$orientation = strtolower($orientation);
		if ($orientation === 'p' || $orientation == 'portrait') {
			$orientation = 'P';
			$this->wPt = $this->fwPt;
			$this->hPt = $this->fhPt;
		} elseif ($orientation === 'l' || $orientation == 'landscape') {
			$orientation = 'L';
			$this->wPt = $this->fhPt;
			$this->hPt = $this->fwPt;
		} else {
			throw new \Mpdf\MpdfException('Incorrect orientation: ' . $orientation);
		}

		$this->CurOrientation = $orientation;

		$this->w = $this->wPt / Mpdf::SCALE;
		$this->h = $this->hPt / Mpdf::SCALE;
	}

	/* -- BACKGROUNDS -- */

	function _resizeBackgroundImage($imw, $imh, $cw, $ch, $resize, $repx, $repy, $pba = [], $size = [])
	{
		// pba is background positioning area (from CSS background-origin) may not always be set [x,y,w,h]
		// size is from CSS3 background-size - takes precendence over old resize
		// $w - absolute length or % or auto or cover | contain
		// $h - absolute length or % or auto or cover | contain
		if (isset($pba['w'])) {
			$cw = $pba['w'];
		}
		if (isset($pba['h'])) {
			$ch = $pba['h'];
		}

		$cw = $cw * Mpdf::SCALE;
		$ch = $ch * Mpdf::SCALE;
		if (empty($size) && !$resize) {
			return [$imw, $imh, $repx, $repy];
		}

		if (isset($size['w']) && $size['w']) {
			if ($size['w'] == 'contain') {
				// Scale the image, while preserving its intrinsic aspect ratio (if any),
				// to the largest size such that both its width and its height can fit inside the background positioning area.
				// Same as resize==3
				$h = $imh * $cw / $imw;
				$w = $cw;
				if ($h > $ch) {
					$w = $w * $ch / $h;
					$h = $ch;
				}
			} elseif ($size['w'] == 'cover') {
				// Scale the image, while preserving its intrinsic aspect ratio (if any),
				// to the smallest size such that both its width and its height can completely cover the background positioning area.
				$h = $imh * $cw / $imw;
				$w = $cw;
				if ($h < $ch) {
					$w = $w * $h / $ch;
					$h = $ch;
				}
			} else {
				if (stristr($size['w'], '%')) {
					$size['w'] = (float) $size['w'];
					$size['w'] /= 100;
					$size['w'] = ($cw * $size['w']);
				}
				if (stristr($size['h'], '%')) {
					$size['h'] = (float) $size['h'];
					$size['h'] /= 100;
					$size['h'] = ($ch * $size['h']);
				}
				if ($size['w'] == 'auto' && $size['h'] == 'auto') {
					$w = $imw;
					$h = $imh;
				} elseif ($size['w'] == 'auto' && $size['h'] != 'auto') {
					$w = $imw * $size['h'] / $imh;
					$h = $size['h'];
				} elseif ($size['w'] != 'auto' && $size['h'] == 'auto') {
					$h = $imh * $size['w'] / $imw;
					$w = $size['w'];
				} else {
					$w = $size['w'];
					$h = $size['h'];
				}
			}
			return [$w, $h, $repx, $repy];
		} elseif ($resize == 1 && $imw > $cw) {
			$h = $imh * $cw / $imw;
			return [$cw, $h, $repx, $repy];
		} elseif ($resize == 2 && $imh > $ch) {
			$w = $imw * $ch / $imh;
			return [$w, $ch, $repx, $repy];
		} elseif ($resize == 3) {
			$w = $imw;
			$h = $imh;
			if ($w > $cw) {
				$h = $h * $cw / $w;
				$w = $cw;
			}
			if ($h > $ch) {
				$w = $w * $ch / $h;
				$h = $ch;
			}
			return [$w, $h, $repx, $repy];
		} elseif ($resize == 4) {
			$h = $imh * $cw / $imw;
			return [$cw, $h, $repx, $repy];
		} elseif ($resize == 5) {
			$w = $imw * $ch / $imh;
			return [$w, $ch, $repx, $repy];
		} elseif ($resize == 6) {
			return [$cw, $ch, $repx, $repy];
		}
		return [$imw, $imh, $repx, $repy];
	}

	function _setClippingPath($clx, $cly, $clw, $clh)
	{
		$s = ' q 0 w '; // Line width=0
		$s .= sprintf('%.3F %.3F m ', ($clx) * Mpdf::SCALE, ($this->h - ($cly)) * Mpdf::SCALE); // start point TL before the arc
		$s .= sprintf('%.3F %.3F l ', ($clx) * Mpdf::SCALE, ($this->h - ($cly + $clh)) * Mpdf::SCALE); // line to BL
		$s .= sprintf('%.3F %.3F l ', ($clx + $clw) * Mpdf::SCALE, ($this->h - ($cly + $clh)) * Mpdf::SCALE); // line to BR
		$s .= sprintf('%.3F %.3F l ', ($clx + $clw) * Mpdf::SCALE, ($this->h - ($cly)) * Mpdf::SCALE); // line to TR
		$s .= sprintf('%.3F %.3F l ', ($clx) * Mpdf::SCALE, ($this->h - ($cly)) * Mpdf::SCALE); // line to TL
		$s .= ' W n '; // Ends path no-op & Sets the clipping path
		return $s;
	}

	// mPDF 6 pagebreaktype
	function _preForcedPagebreak($pagebreaktype)
	{
		if ($pagebreaktype == 'cloneall') {
			// Close any open block tags
			$arr = [];
			$ai = 0;
			for ($b = $this->blklvl; $b > 0; $b--) {
				$this->tag->CloseTag($this->blk[$b]['tag'], $arr, $ai);
			}
			if ($this->blklvl == 0 && !empty($this->textbuffer)) { // Output previously buffered content
				$this->printbuffer($this->textbuffer, 1);
				$this->textbuffer = [];
			}
		} elseif ($pagebreaktype == 'clonebycss') {
			// Close open block tags whilst box-decoration-break==clone
			$arr = [];
			$ai = 0;
			for ($b = $this->blklvl; $b > 0; $b--) {
				if (isset($this->blk[$b]['box_decoration_break']) && $this->blk[$b]['box_decoration_break'] == 'clone') {
					$this->tag->CloseTag($this->blk[$b]['tag'], $arr, $ai);
				} else {
					if ($b == $this->blklvl && !empty($this->textbuffer)) { // Output previously buffered content
						$this->printbuffer($this->textbuffer, 1);
						$this->textbuffer = [];
					}
					break;
				}
			}
		} elseif (!empty($this->textbuffer)) { // Output previously buffered content
			$this->printbuffer($this->textbuffer, 1);
			$this->textbuffer = [];
		}
	}

	// mPDF 6 pagebreaktype
	function _postForcedPagebreak($pagebreaktype, $startpage, $save_blk, $save_blklvl)
	{
		if ($pagebreaktype == 'cloneall') {
			$this->blk = [];
			$this->blk[0] = $save_blk[0];
			// Re-open block tags
			$this->blklvl = 0;
			$arr = [];
			$i = 0;
			for ($b = 1; $b <= $save_blklvl; $b++) {
				$this->tag->OpenTag($save_blk[$b]['tag'], $save_blk[$b]['attr'], $arr, $i);
			}
		} elseif ($pagebreaktype == 'clonebycss') {
			$this->blk = [];
			$this->blk[0] = $save_blk[0];
			// Don't re-open tags for lowest level elements - so need to do some adjustments
			for ($b = 1; $b <= $this->blklvl; $b++) {
				$this->blk[$b] = $save_blk[$b];
				$this->blk[$b]['startpage'] = 0;
				$this->blk[$b]['y0'] = $this->y; // ?? $this->tMargin
				if (($this->page - $startpage) % 2) {
					if (isset($this->blk[$b]['x0'])) {
						$this->blk[$b]['x0'] += $this->MarginCorrection;
					} else {
						$this->blk[$b]['x0'] = $this->MarginCorrection;
					}
				}
				// for Float DIV
				$this->blk[$b]['marginCorrected'][$this->page] = true;
			}

			// Re-open block tags for any that have box_decoration_break==clone
			$arr = [];
			$i = 0;
			for ($b = $this->blklvl + 1; $b <= $save_blklvl; $b++) {
				if ($b < $this->blklvl) {
					$this->lastblocklevelchange = -1;
				}
				$this->tag->OpenTag($save_blk[$b]['tag'], $save_blk[$b]['attr'], $arr, $i);
			}
			if ($this->blk[$this->blklvl]['box_decoration_break'] != 'clone') {
				$this->lastblocklevelchange = -1;
			}
		} else {
			$this->lastblocklevelchange = -1;
		}
	}

	function _getCharWidth(&$cw, $u, $isdef = true)
	{
		$w = 0;

		if ($u == 0) {
			$w = false;
		} elseif (isset($cw[$u * 2 + 1])) {
			$w = (ord($cw[$u * 2]) << 8) + ord($cw[$u * 2 + 1]);
		}

		if ($w == 65535) {
			return 0;
		} elseif ($w) {
			return $w;
		} elseif ($isdef) {
			return false;
		} else {
			return 0;
		}
	}

	function _charDefined(&$cw, $u)
	{
		$w = 0;
		if ($u == 0) {
			return false;
		}
		if (isset($cw[$u * 2 + 1])) {
			$w = (ord($cw[$u * 2]) << 8) + ord($cw[$u * 2 + 1]);
		}
		if ($w) {
			return true;
		} else {
			return false;
		}
	}

	function _kern($txt, $mode, $aix, $x, $y)
	{
		if ($mode == 'MBTw') { // Multibyte requiring word spacing
			$space = ' ';
			// Convert string to UTF-16BE without BOM
			$space = $this->UTF8ToUTF16BE($space, false);
			$space = $this->_escape($space);
			$s = sprintf(' BT ' . $aix, $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE);
			$t = explode(' ', $txt);
			for ($i = 0; $i < count($t); $i++) {
				$tx = $t[$i];

				$tj = '(';
				$unicode = $this->UTF8StringToArray($tx);
				for ($ti = 0; $ti < count($unicode); $ti++) {
					if ($ti > 0 && isset($this->CurrentFont['kerninfo'][$unicode[($ti - 1)]][$unicode[$ti]])) {
						$kern = -$this->CurrentFont['kerninfo'][$unicode[($ti - 1)]][$unicode[$ti]];
						$tj .= sprintf(')%d(', $kern);
					}
					$tc = UtfString::code2utf($unicode[$ti]);
					$tc = $this->UTF8ToUTF16BE($tc, false);
					$tj .= $this->_escape($tc);
				}
				$tj .= ')';
				$s.=sprintf(' %.3F Tc [%s] TJ', $this->charspacing, $tj);


				if (($i + 1) < count($t)) {
					$s.=sprintf(' %.3F Tc (%s) Tj', $this->ws + $this->charspacing, $space);
				}
			}
			$s.=' ET ';
		} elseif (!$this->usingCoreFont) {
			$s = '';
			$tj = '(';
			$unicode = $this->UTF8StringToArray($txt);
			for ($i = 0; $i < count($unicode); $i++) {
				if ($i > 0 && isset($this->CurrentFont['kerninfo'][$unicode[($i - 1)]][$unicode[$i]])) {
					$kern = -$this->CurrentFont['kerninfo'][$unicode[($i - 1)]][$unicode[$i]];
					$tj .= sprintf(')%d(', $kern);
				}
				$tx = UtfString::code2utf($unicode[$i]);
				$tx = $this->UTF8ToUTF16BE($tx, false);
				$tj .= $this->_escape($tx);
			}
			$tj .= ')';
			$s.=sprintf(' BT ' . $aix . ' [%s] TJ ET ', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, $tj);
		} else { // CORE Font
			$s = '';
			$tj = '(';
			$l = strlen($txt);
			for ($i = 0; $i < $l; $i++) {
				if ($i > 0 && isset($this->CurrentFont['kerninfo'][$txt[($i - 1)]][$txt[$i]])) {
					$kern = -$this->CurrentFont['kerninfo'][$txt[($i - 1)]][$txt[$i]];
					$tj .= sprintf(')%d(', $kern);
				}
				$tj .= $this->_escape($txt[$i]);
			}
			$tj .= ')';
			$s.=sprintf(' BT ' . $aix . ' [%s] TJ ET ', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, $tj);
		}

		return $s;
	}

	function _printListBullet($x, $y, $size, $type, $color)
	{
		// x and y are the centre of the bullet; size is the width and/or height in mm
		$fcol = $this->SetTColor($color, true);
		$lcol = strtoupper($fcol); // change 0 0 0 rg to 0 0 0 RG
		$this->_out(sprintf('q %s %s', $lcol, $fcol));
		$this->_out('0 j 0 J [] 0 d');
		if ($type == 'square') {
			$size *= 0.85; // Smaller to appear the same size as circle/disc
			$this->_out(sprintf('%.3F %.3F %.3F %.3F re f', ($x - $size / 2) * Mpdf::SCALE, ($this->h - $y + $size / 2) * Mpdf::SCALE, ($size) * Mpdf::SCALE, (-$size) * Mpdf::SCALE));
		} elseif ($type == 'disc') {
			$this->Circle($x, $y, $size / 2, 'F'); // Fill
		} elseif ($type == 'circle') {
			$lw = $size / 12; // Line width
			$this->_out(sprintf('%.3F w ', $lw * Mpdf::SCALE));
			$this->Circle($x, $y, $size / 2 - $lw / 2, 'S'); // Stroke
		}
		$this->_out('Q');
	}

	// mPDF 6
	// Get previous character and move pointers
	function _moveToPrevChar(&$contentctr, &$charctr, $content)
	{
		$lastchar = false;
		$charctr--;
		while ($charctr < 0) { // go back to previous $content[]
			$contentctr--;
			if ($contentctr < 0) {
				return false;
			}
			if ($this->usingCoreFont) {
				$charctr = strlen($content[$contentctr]) - 1;
			} else {
				$charctr = mb_strlen($content[$contentctr], $this->mb_enc) - 1;
			}
		}
		if ($this->usingCoreFont) {
			$lastchar = $content[$contentctr][$charctr];
		} else {
			$lastchar = mb_substr($content[$contentctr], $charctr, 1, $this->mb_enc);
		}
		return $lastchar;
	}

	// Get previous character
	function _getPrevChar($contentctr, $charctr, $content)
	{
		$lastchar = false;
		$charctr--;
		while ($charctr < 0) { // go back to previous $content[]
			$contentctr--;
			if ($contentctr < 0) {
				return false;
			}
			if ($this->usingCoreFont) {
				$charctr = strlen($content[$contentctr]) - 1;
			} else {
				$charctr = mb_strlen($content[$contentctr], $this->mb_enc) - 1;
			}
		}
		if ($this->usingCoreFont) {
			$lastchar = $content[$contentctr][$charctr];
		} else {
			$lastchar = mb_substr($content[$contentctr], $charctr, 1, $this->mb_enc);
		}
		return $lastchar;
	}

	// ----------------------END OF FLOWING BLOCK------------------------------------//


	/* -- CSS-IMAGE-FLOAT -- */
	// Update values if set to skipline
	function _advanceFloatMargins()
	{
		// Update floatmargins - L
		if (isset($this->floatmargins['L']) && $this->floatmargins['L']['skipline'] && $this->floatmargins['L']['y0'] != $this->y) {
			$yadj = $this->y - $this->floatmargins['L']['y0'];
			$this->floatmargins['L']['y0'] = $this->y;
			$this->floatmargins['L']['y1'] += $yadj;

			// Update objattr in floatbuffer
			if ($this->floatbuffer[$this->floatmargins['L']['id']]['border_left']['w']) {
				$this->floatbuffer[$this->floatmargins['L']['id']]['BORDER-Y'] += $yadj;
			}
			$this->floatbuffer[$this->floatmargins['L']['id']]['INNER-Y'] += $yadj;
			$this->floatbuffer[$this->floatmargins['L']['id']]['OUTER-Y'] += $yadj;

			// Unset values
			$this->floatbuffer[$this->floatmargins['L']['id']]['skipline'] = false;
			$this->floatmargins['L']['skipline'] = false;
			$this->floatmargins['L']['id'] = '';
		}
		// Update floatmargins - R
		if (isset($this->floatmargins['R']) && $this->floatmargins['R']['skipline'] && $this->floatmargins['R']['y0'] != $this->y) {
			$yadj = $this->y - $this->floatmargins['R']['y0'];
			$this->floatmargins['R']['y0'] = $this->y;
			$this->floatmargins['R']['y1'] += $yadj;

			// Update objattr in floatbuffer
			if ($this->floatbuffer[$this->floatmargins['R']['id']]['border_left']['w']) {
				$this->floatbuffer[$this->floatmargins['R']['id']]['BORDER-Y'] += $yadj;
			}
			$this->floatbuffer[$this->floatmargins['R']['id']]['INNER-Y'] += $yadj;
			$this->floatbuffer[$this->floatmargins['R']['id']]['OUTER-Y'] += $yadj;

			// Unset values
			$this->floatbuffer[$this->floatmargins['R']['id']]['skipline'] = false;
			$this->floatmargins['R']['skipline'] = false;
			$this->floatmargins['R']['id'] = '';
		}
	}

	/* -- END CSS-IMAGE-FLOAT -- */



	/* -- END HTML-CSS -- */

	function _SetTextRendering($mode)
	{
		if (!(($mode == 0) || ($mode == 1) || ($mode == 2))) {
			throw new \Mpdf\MpdfException("Text rendering mode should be 0, 1 or 2 (value : $mode)");
		}
		$tr = ($mode . ' Tr');
		if ($this->page > 0 && ((isset($this->pageoutput[$this->page]['TextRendering']) && $this->pageoutput[$this->page]['TextRendering'] != $tr) || !isset($this->pageoutput[$this->page]['TextRendering']))) {
			$this->_out($tr);
		}
		$this->pageoutput[$this->page]['TextRendering'] = $tr;
	}

	// =============================================================
	// =============================================================
	// =============================================================
	// =============================================================
	// =============================================================
	/* -- HTML-CSS -- */

	function _getObjAttr($t)
	{
		$c = explode("\xbb\xa4\xac", $t, 2);
		$c = explode(",", $c[1], 2);
		foreach ($c as $v) {
			$v = explode("=", $v, 2);
			$sp[$v[0]] = $v[1];
		}
		return (unserialize($sp['objattr']));
	}

	// *****************************************************************************
	//                                                                             *
	//                             Protected methods                               *
	//                                                                             *
	// *****************************************************************************
	function _dochecks()
	{
		// Check for locale-related bug
		if (1.1 == 1) {
			throw new \Mpdf\MpdfException('Do not alter the locale before including mPDF');
		}

		// Check for decimal separator
		if (sprintf('%.1f', 1.0) != '1.0') {
			setlocale(LC_NUMERIC, 'C');
		}

		if (ini_get('mbstring.func_overload')) {
			throw new \Mpdf\MpdfException('Mpdf cannot function properly with mbstring.func_overload enabled');
		}

		if (!function_exists('mb_substr')) {
			throw new \Mpdf\MpdfException('mbstring extension must be loaded in order to run mPDF');
		}
	}

	function _puthtmlheaders()
	{
		$this->state = 2;
		$nb = $this->page;
		for ($n = 1; $n <= $nb; $n++) {
			if ($this->mirrorMargins && $n % 2 == 0) {
				$OE = 'E';
			} // EVEN
			else {
				$OE = 'O';
			}
			$this->page = $n;
			$pn = $this->docPageNum($n);
			if ($pn) {
				$pnstr = $this->pagenumPrefix . $pn . $this->pagenumSuffix;
			} else {
				$pnstr = '';
			}

			$pnt = $this->docPageNumTotal($n);

			if ($pnt) {
				$pntstr = $this->nbpgPrefix . $pnt . $this->nbpgSuffix;
			} else {
				$pntstr = '';
			}

			if (isset($this->saveHTMLHeader[$n][$OE])) {
				$html = isset($this->saveHTMLHeader[$n][$OE]['html']) ? $this->saveHTMLHeader[$n][$OE]['html'] : '';
				$this->lMargin = $this->saveHTMLHeader[$n][$OE]['ml'];
				$this->rMargin = $this->saveHTMLHeader[$n][$OE]['mr'];
				$this->tMargin = $this->saveHTMLHeader[$n][$OE]['mh'];
				$this->bMargin = $this->saveHTMLHeader[$n][$OE]['mf'];
				$this->margin_header = $this->saveHTMLHeader[$n][$OE]['mh'];
				$this->margin_footer = $this->saveHTMLHeader[$n][$OE]['mf'];
				$this->w = $this->saveHTMLHeader[$n][$OE]['pw'];
				$this->h = $this->saveHTMLHeader[$n][$OE]['ph'];
				$rotate = (isset($this->saveHTMLHeader[$n][$OE]['rotate']) ? $this->saveHTMLHeader[$n][$OE]['rotate'] : null);
				$this->Reset();
				$this->pageoutput[$n] = [];
				$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
				$this->x = $this->lMargin;
				$this->y = $this->margin_header;
				$html = str_replace('{PAGENO}', $pnstr, $html);
				$html = str_replace($this->aliasNbPgGp, $pntstr, $html); // {nbpg}
				$html = str_replace($this->aliasNbPg, $nb, $html); // {nb}
				$html = preg_replace_callback('/\{DATE\s+(.*?)\}/', [$this, 'date_callback'], $html); // mPDF 5.7

				$this->HTMLheaderPageLinks = [];
				$this->HTMLheaderPageAnnots = [];
				$this->HTMLheaderPageForms = [];
				$this->pageBackgrounds = [];

				$this->writingHTMLheader = true;
				$this->WriteHTML($html, 4); // parameter 4 saves output to $this->headerbuffer
				$this->writingHTMLheader = false;
				$this->Reset();
				$this->pageoutput[$n] = [];

				$s = $this->PrintPageBackgrounds();
				$this->headerbuffer = $s . $this->headerbuffer;
				$os = '';
				if ($rotate) {
					$os .= sprintf('q 0 -1 1 0 0 %.3F cm ', ($this->w * Mpdf::SCALE));
					// To rotate the other way i.e. Header to left of page:
					// $os .= sprintf('q 0 1 -1 0 %.3F %.3F cm ',($this->h*Mpdf::SCALE), (($this->rMargin - $this->lMargin )*Mpdf::SCALE));
				}
				$os .= $this->headerbuffer;
				if ($rotate) {
					$os .= ' Q' . "\n";
				}

				// Writes over the page background but behind any other output on page
				$os = preg_replace(['/\\\\/', '/\$/'], ['\\\\\\\\', '\\\\$'], $os);

				$this->pages[$n] = preg_replace('/(___HEADER___MARKER' . $this->uniqstr . ')/', "\n" . $os . "\n" . '\\1', $this->pages[$n]);

				$lks = $this->HTMLheaderPageLinks;
				foreach ($lks as $lk) {
					if ($rotate) {
						$lw = $lk[2];
						$lh = $lk[3];
						$lk[2] = $lh;
						$lk[3] = $lw; // swap width and height
						$ax = $lk[0] / Mpdf::SCALE;
						$ay = $lk[1] / Mpdf::SCALE;
						$bx = $ay - ($lh / Mpdf::SCALE);
						$by = $this->w - $ax;
						$lk[0] = $bx * Mpdf::SCALE;
						$lk[1] = ($this->h - $by) * Mpdf::SCALE - $lw;
					}
					$this->PageLinks[$n][] = $lk;
				}
				/* -- FORMS -- */
				foreach ($this->HTMLheaderPageForms as $f) {
					$this->form->forms[$f['n']] = $f;
				}
				/* -- END FORMS -- */
			}

			if (isset($this->saveHTMLFooter[$n][$OE])) {

				$html = $this->saveHTMLFooter[$this->page][$OE]['html'];

				$this->lMargin = $this->saveHTMLFooter[$n][$OE]['ml'];
				$this->rMargin = $this->saveHTMLFooter[$n][$OE]['mr'];
				$this->tMargin = $this->saveHTMLFooter[$n][$OE]['mh'];
				$this->bMargin = $this->saveHTMLFooter[$n][$OE]['mf'];
				$this->margin_header = $this->saveHTMLFooter[$n][$OE]['mh'];
				$this->margin_footer = $this->saveHTMLFooter[$n][$OE]['mf'];
				$this->w = $this->saveHTMLFooter[$n][$OE]['pw'];
				$this->h = $this->saveHTMLFooter[$n][$OE]['ph'];
				$rotate = (isset($this->saveHTMLFooter[$n][$OE]['rotate']) ? $this->saveHTMLFooter[$n][$OE]['rotate'] : null);
				$this->Reset();
				$this->pageoutput[$n] = [];
				$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
				$this->x = $this->lMargin;
				$top_y = $this->y = $this->h - $this->margin_footer;

				// if bottom-margin==0, corrects to avoid division by zero
				if ($this->y == $this->h) {
					$top_y = $this->y = ($this->h + 0.01);
				}

				$html = str_replace('{PAGENO}', $pnstr, $html);
				$html = str_replace($this->aliasNbPgGp, $pntstr, $html); // {nbpg}
				$html = str_replace($this->aliasNbPg, $nb, $html); // {nb}
				$html = preg_replace_callback('/\{DATE\s+(.*?)\}/', [$this, 'date_callback'], $html); // mPDF 5.7


				$this->HTMLheaderPageLinks = [];
				$this->HTMLheaderPageAnnots = [];
				$this->HTMLheaderPageForms = [];
				$this->pageBackgrounds = [];

				$this->writingHTMLfooter = true;
				$this->InFooter = true;
				$this->WriteHTML($html, 4); // parameter 4 saves output to $this->headerbuffer
				$this->InFooter = false;
				$this->Reset();
				$this->pageoutput[$n] = [];

				$fheight = $this->y - $top_y;
				$adj = -$fheight;

				$s = $this->PrintPageBackgrounds(-$adj);
				$this->headerbuffer = $s . $this->headerbuffer;
				$this->writingHTMLfooter = false; // mPDF 5.7.3  (moved after PrintPageBackgrounds so can adjust position of images in footer)

				$os = '';
				$os .= $this->StartTransform(true) . "\n";

				if ($rotate) {
					$os .= sprintf('q 0 -1 1 0 0 %.3F cm ', ($this->w * Mpdf::SCALE));
					// To rotate the other way i.e. Header to left of page:
					// $os .= sprintf('q 0 1 -1 0 %.3F %.3F cm ',($this->h*Mpdf::SCALE), (($this->rMargin - $this->lMargin )*Mpdf::SCALE));
				}

				$os .= $this->transformTranslate(0, $adj, true) . "\n";
				$os .= $this->headerbuffer;

				if ($rotate) {
					$os .= ' Q' . "\n";
				}

				$os .= $this->StopTransform(true) . "\n";

				// Writes over the page background but behind any other output on page
				$os = preg_replace(['/\\\\/', '/\$/'], ['\\\\\\\\', '\\\\$'], $os);

				$this->pages[$n] = preg_replace('/(___HEADER___MARKER' . $this->uniqstr . ')/', "\n" . $os . "\n" . '\\1', $this->pages[$n]);

				$lks = $this->HTMLheaderPageLinks;

				foreach ($lks as $lk) {

					$lk[1] -= $adj * Mpdf::SCALE;

					if ($rotate) {
						$lw = $lk[2];
						$lh = $lk[3];
						$lk[2] = $lh;
						$lk[3] = $lw; // swap width and height

						$ax = $lk[0] / Mpdf::SCALE;
						$ay = $lk[1] / Mpdf::SCALE;
						$bx = $ay - ($lh / Mpdf::SCALE);
						$by = $this->w - $ax;
						$lk[0] = $bx * Mpdf::SCALE;
						$lk[1] = ($this->h - $by) * Mpdf::SCALE - $lw;
					}

					$this->PageLinks[$n][] = $lk;
				}

				/* -- FORMS -- */
				foreach ($this->HTMLheaderPageForms as $f) {
					$f['y'] += $adj;
					$this->form->forms[$f['n']] = $f;
				}
				/* -- END FORMS -- */
			}
		}

		$this->page = $nb;
		$this->state = 1;
	}

	function _putpages()
	{
		$nb = $this->page;
		$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';

		if ($this->DefOrientation == 'P') {
			$defwPt = $this->fwPt;
			$defhPt = $this->fhPt;
		} else {
			$defwPt = $this->fhPt;
			$defhPt = $this->fwPt;
		}
		$annotid = (3 + 2 * $nb);

		// Active Forms
		$totaladdnum = 0;
		for ($n = 1; $n <= $nb; $n++) {
			if (isset($this->PageLinks[$n])) {
				$totaladdnum += count($this->PageLinks[$n]);
			}
			/* -- ANNOTATIONS -- */
			if (isset($this->PageAnnots[$n])) {
				foreach ($this->PageAnnots[$n] as $k => $pl) {
					if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
						$totaladdnum += 2;
					} else {
						$totaladdnum++;
					}
				}
			}
			/* -- END ANNOTATIONS -- */

			/* -- FORMS -- */
			if (count($this->form->forms) > 0) {
				$this->form->countPageForms($n, $totaladdnum);
			}
			/* -- END FORMS -- */
		}
		/* -- FORMS -- */
		// Make a note in the radio button group of the obj_id it will have
		$ctr = 0;
		if (count($this->form->form_radio_groups)) {
			foreach ($this->form->form_radio_groups as $name => $frg) {
				$this->form->form_radio_groups[$name]['obj_id'] = $annotid + $totaladdnum + $ctr;
				$ctr++;
			}
		}
		/* -- END FORMS -- */

		// Select unused fonts (usually default font)
		$unused = [];
		foreach ($this->fonts as $fk => $font) {
			if (isset($font['type']) && $font['type'] == 'TTF' && !$font['used']) {
				$unused[] = $fk;
			}
		}


		for ($n = 1; $n <= $nb; $n++) {
			$thispage = $this->pages[$n];
			if (isset($this->OrientationChanges[$n])) {
				$hPt = $this->pageDim[$n]['w'] * Mpdf::SCALE;
				$wPt = $this->pageDim[$n]['h'] * Mpdf::SCALE;
				$owidthPt_LR = $this->pageDim[$n]['outer_width_TB'] * Mpdf::SCALE;
				$owidthPt_TB = $this->pageDim[$n]['outer_width_LR'] * Mpdf::SCALE;
			} else {
				$wPt = $this->pageDim[$n]['w'] * Mpdf::SCALE;
				$hPt = $this->pageDim[$n]['h'] * Mpdf::SCALE;
				$owidthPt_LR = $this->pageDim[$n]['outer_width_LR'] * Mpdf::SCALE;
				$owidthPt_TB = $this->pageDim[$n]['outer_width_TB'] * Mpdf::SCALE;
			}
			// Remove references to unused fonts (usually default font)
			foreach ($unused as $fk) {
				if ($this->fonts[$fk]['sip'] || $this->fonts[$fk]['smp']) {
					foreach ($this->fonts[$fk]['subsetfontids'] as $k => $fid) {
						$thispage = preg_replace('/\s\/F' . $fid . ' \d[\d.]* Tf\s/is', ' ', $thispage);
					}
				} else {
					$thispage = preg_replace('/\s\/F' . $this->fonts[$fk]['i'] . ' \d[\d.]* Tf\s/is', ' ', $thispage);
				}
			}
			// Clean up repeated /GS1 gs statements
			// For some reason using + for repetition instead of {2,20} crashes PHP Script Interpreter ???
			$thispage = preg_replace('/(\/GS1 gs\n){2,20}/', "/GS1 gs\n", $thispage);

			$thispage = preg_replace('/(\s*___BACKGROUND___PATTERNS' . $this->uniqstr . '\s*)/', " ", $thispage);
			$thispage = preg_replace('/(\s*___HEADER___MARKER' . $this->uniqstr . '\s*)/', " ", $thispage);
			$thispage = preg_replace('/(\s*___PAGE___START' . $this->uniqstr . '\s*)/', " ", $thispage);
			$thispage = preg_replace('/(\s*___TABLE___BACKGROUNDS' . $this->uniqstr . '\s*)/', " ", $thispage);
			// mPDF 5.7.3 TRANSFORMS
			while (preg_match('/(\% BTR(.*?)\% ETR)/is', $thispage, $m)) {
				$thispage = preg_replace('/(\% BTR.*?\% ETR)/is', '', $thispage, 1) . "\n" . $m[2];
			}

			// Page
			$this->_newobj();
			$this->_out('<</Type /Page');
			$this->_out('/Parent 1 0 R');
			if (isset($this->OrientationChanges[$n])) {
				$this->_out(sprintf('/MediaBox [0 0 %.3F %.3F]', $hPt, $wPt));
				// If BleedBox is defined, it must be larger than the TrimBox, but smaller than the MediaBox
				$bleedMargin = $this->pageDim[$n]['bleedMargin'] * Mpdf::SCALE;
				if ($bleedMargin && ($owidthPt_TB || $owidthPt_LR)) {
					$x0 = $owidthPt_TB - $bleedMargin;
					$y0 = $owidthPt_LR - $bleedMargin;
					$x1 = $hPt - $owidthPt_TB + $bleedMargin;
					$y1 = $wPt - $owidthPt_LR + $bleedMargin;
					$this->_out(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
				}
				$this->_out(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidthPt_TB, $owidthPt_LR, ($hPt - $owidthPt_TB), ($wPt - $owidthPt_LR)));
				if (isset($this->OrientationChanges[$n]) && $this->displayDefaultOrientation) {
					if ($this->DefOrientation == 'P') {
						$this->_out('/Rotate 270');
					} else {
						$this->_out('/Rotate 90');
					}
				}
			} // elseif($wPt != $defwPt || $hPt != $defhPt) {
			else {
				$this->_out(sprintf('/MediaBox [0 0 %.3F %.3F]', $wPt, $hPt));
				$bleedMargin = $this->pageDim[$n]['bleedMargin'] * Mpdf::SCALE;
				if ($bleedMargin && ($owidthPt_TB || $owidthPt_LR)) {
					$x0 = $owidthPt_LR - $bleedMargin;
					$y0 = $owidthPt_TB - $bleedMargin;
					$x1 = $wPt - $owidthPt_LR + $bleedMargin;
					$y1 = $hPt - $owidthPt_TB + $bleedMargin;
					$this->_out(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
				}
				$this->_out(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidthPt_LR, $owidthPt_TB, ($wPt - $owidthPt_LR), ($hPt - $owidthPt_TB)));
			}
			$this->_out('/Resources 2 0 R');

			// Important to keep in RGB colorSpace when using transparency
			if (!$this->PDFA && !$this->PDFX) {
				if ($this->restrictColorSpace == 3) {
					$this->_out('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >> ');
				} elseif ($this->restrictColorSpace == 1) {
					$this->_out('/Group << /Type /Group /S /Transparency /CS /DeviceGray >> ');
				} else {
					$this->_out('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >> ');
				}
			}

			$annotsnum = 0;
			$embeddedfiles = []; // mPDF 5.7.2 /EmbeddedFiles

			if (isset($this->PageLinks[$n])) {
				$annotsnum += count($this->PageLinks[$n]);
			}
			/* -- ANNOTATIONS -- */
			if (isset($this->PageAnnots[$n])) {
				foreach ($this->PageAnnots[$n] as $k => $pl) {
					if (!empty($pl['opt']['file'])) {
						$embeddedfiles[$annotsnum + 1] = true;
					} // mPDF 5.7.2 /EmbeddedFiles
					if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
						$annotsnum += 2;
					} else {
						$annotsnum++;
					}
					$this->PageAnnots[$n][$k]['pageobj'] = $this->n;
				}
			}
			/* -- END ANNOTATIONS -- */

			/* -- FORMS -- */
			// Active Forms
			$formsnum = 0;
			if (count($this->form->forms) > 0) {
				foreach ($this->form->forms as $val) {
					if ($val['page'] == $n) {
						$formsnum++;
					}
				}
			}
			/* -- END FORMS -- */
			if ($annotsnum || $formsnum) {
				$s = '/Annots [ ';
				for ($i = 0; $i < $annotsnum; $i++) {
					if (!isset($embeddedfiles[$i])) {
						$s .= ($annotid + $i) . ' 0 R ';
					} // mPDF 5.7.2 /EmbeddedFiles
				}
				$annotid += $annotsnum;
				/* -- FORMS -- */
				if (count($this->form->forms) > 0) {
					$this->form->addFormIds($n, $s, $annotid);
				}
				/* -- END FORMS -- */
				$s .= '] ';
				$this->_out($s);
			}

			$this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
			$this->_out('endobj');

			// Page content
			$this->_newobj();
			$p = ($this->compress) ? gzcompress($thispage) : $thispage;
			$this->_out('<<' . $filter . '/Length ' . strlen($p) . '>>');
			$this->_putstream($p);
			$this->_out('endobj');
		}
		$this->_putannots(); // mPDF 5.7.2
		// Pages root
		$this->offsets[1] = strlen($this->buffer);
		$this->_out('1 0 obj');
		$this->_out('<</Type /Pages');
		$kids = '/Kids [';
		for ($i = 0; $i < $nb; $i++) {
			$kids.=(3 + 2 * $i) . ' 0 R ';
		}
		$this->_out($kids . ']');
		$this->_out('/Count ' . $nb);
		$this->_out(sprintf('/MediaBox [0 0 %.3F %.3F]', $defwPt, $defhPt));
		$this->_out('>>');
		$this->_out('endobj');
	}

	/**
	 * @since 5.7.2
	 */
	function _putannots()
	{
		$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';

		$nb = $this->page;

		for ($n = 1; $n <= $nb; $n++) {

			$annotobjs = [];

			if (isset($this->PageLinks[$n]) || isset($this->PageAnnots[$n]) || count($this->form->forms) > 0) {

				$wPt = $this->pageDim[$n]['w'] * Mpdf::SCALE;
				$hPt = $this->pageDim[$n]['h'] * Mpdf::SCALE;

				// Links
				if (isset($this->PageLinks[$n])) {

					foreach ($this->PageLinks[$n] as $key => $pl) {

						$this->_newobj();
						$annot = '';

						$rect = sprintf('%.3F %.3F %.3F %.3F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);

						$annot .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . ']';
						// Removed as causing undesired effects in Chrome PDF viewer https://github.com/mpdf/mpdf/issues/283
						// $annot .= ' /Contents ' . $this->_UTF16BEtextstring($pl[4]);
						$annot .= ' /NM ' . $this->_textstring(sprintf('%04u-%04u', $n, $key));
						$annot .= ' /M ' . $this->_textstring('D:' . date('YmdHis'));

						$annot .= ' /Border [0 0 0]';

						// Use this (instead of /Border) to specify border around link

						// $annot .= ' /BS <</W 1';	// Width on points; 0 = no line
						// $annot .= ' /S /D';		// style - [S]olid, [D]ashed, [B]eveled, [I]nset, [U]nderline
						// $annot .= ' /D [3 2]';		// Dash array - if dashed
						// $annot .= ' >>';
						// $annot .= ' /C [1 0 0]';	// Color RGB

						if ($this->PDFA || $this->PDFX) {
							$annot .= ' /F 28';
						}

						if (strpos($pl[4], '@') === 0) {

							$p = substr($pl[4], 1);
							// $h=isset($this->OrientationChanges[$p]) ? $wPt : $hPt;
							$htarg = $this->pageDim[$p]['h'] * Mpdf::SCALE;
							$annot .= sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $p, $htarg);

						} elseif (is_string($pl[4])) {

							$annot .= ' /A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>> >>';

						} else {

							$l = $this->links[$pl[4]];
							// may not be set if #link points to non-existent target
							if (isset($this->pageDim[$l[0]]['h'])) {
								$htarg = $this->pageDim[$l[0]]['h'] * Mpdf::SCALE;
							} else {
								$htarg = $this->h * Mpdf::SCALE;
							} // doesn't really matter

							$annot .= sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $l[0], $htarg - $l[1] * Mpdf::SCALE);
						}

						$this->_out($annot);
						$this->_out('endobj');

					}
				}

				/* -- ANNOTATIONS -- */
				if (isset($this->PageAnnots[$n])) {

					foreach ($this->PageAnnots[$n] as $key => $pl) {

						$fileAttachment = (bool) $pl['opt']['file'];

						if ($fileAttachment && !$this->allowAnnotationFiles) {
							$this->logger->warning('Embedded files for annotations have to be allowed explicitly with "allowAnnotationFiles" config key');
							$fileAttachment = false;
						}

						$this->_newobj();
						$annot = '';
						$pl['opt'] = array_change_key_case($pl['opt'], CASE_LOWER);
						$x = $pl['x'];

						if ($this->annotMargin <> 0 || $x == 0 || $x < 0) { // Odd page
							$x = ($wPt / Mpdf::SCALE) - $this->annotMargin;
						}

						$w = $h = 0;
						$a = $x * Mpdf::SCALE;
						$b = $hPt - ($pl['y'] * Mpdf::SCALE);

						$annot .= '<</Type /Annot ';

						if ($fileAttachment) {
							$annot .= '/Subtype /FileAttachment ';
							// Need to set a size for FileAttachment icons
							if ($pl['opt']['icon'] == 'Paperclip') {
								$w = 8.235;
								$h = 20;
							} elseif ($pl['opt']['icon'] == 'Tag') {
								$w = 20;
								$h = 16;
							} elseif ($pl['opt']['icon'] == 'Graph') {
								$w = 20;
								$h = 20;
							} else {
								$w = 14;
								$h = 20;
							}

							// PushPin
							$f = $pl['opt']['file'];
							$f = preg_replace('/^.*\//', '', $f);
							$f = preg_replace('/[^a-zA-Z0-9._]/', '', $f);

							$annot .= '/FS <</Type /Filespec /F (' . $f . ')';
							$annot .= '/EF <</F ' . ($this->n + 1) . ' 0 R>>';
							$annot .= '>>';

						} else {
							$annot .= '/Subtype /Text';
							$w = 20;
							$h = 20;  // mPDF 6
						}

						$rect = sprintf('%.3F %.3F %.3F %.3F', $a, $b - $h, $a + $w, $b);
						$annot .= ' /Rect [' . $rect . ']';

						// contents = description of file in free text
						$annot .= ' /Contents ' . $this->_UTF16BEtextstring($pl['txt']);

						$annot .= ' /NM ' . $this->_textstring(sprintf('%04u-%04u', $n, (2000 + $key)));
						$annot .= ' /M ' . $this->_textstring('D:' . date('YmdHis'));
						$annot .= ' /CreationDate ' . $this->_textstring('D:' . date('YmdHis'));
						$annot .= ' /Border [0 0 0]';

						if ($this->PDFA || $this->PDFX) {
							$annot .= ' /F 28';
							$annot .= ' /CA 1';
						} elseif ($pl['opt']['ca'] > 0) {
							$annot .= ' /CA ' . $pl['opt']['ca'];
						}

						$annotcolor = ' /C [';
						if (isset($pl['opt']['c']) and $pl['opt']['c']) {
							$col = $pl['opt']['c'];
							if ($col{0} == 3 || $col{0} == 5) {
								$annotcolor .= sprintf("%.3F %.3F %.3F", ord($col{1}) / 255, ord($col{2}) / 255, ord($col{3}) / 255);
							} elseif ($col{0} == 1) {
								$annotcolor .= sprintf("%.3F", ord($col{1}) / 255);
							} elseif ($col{0} == 4 || $col{0} == 6) {
								$annotcolor .= sprintf("%.3F %.3F %.3F %.3F", ord($col{1}) / 100, ord($col{2}) / 100, ord($col{3}) / 100, ord($col{4}) / 100);
							} else {
								$annotcolor .= '1 1 0';
							}
						} else {
							$annotcolor .= '1 1 0';
						}
						$annotcolor .= ']';
						$annot .= $annotcolor;

						// Usually Author
						// Use as Title for fileattachment
						if (isset($pl['opt']['t']) and is_string($pl['opt']['t'])) {
							$annot .= ' /T ' . $this->_UTF16BEtextstring($pl['opt']['t']);
						}

						if ($fileAttachment) {
							$iconsapp = ['Paperclip', 'Graph', 'PushPin', 'Tag'];
						} else {
							$iconsapp = ['Comment', 'Help', 'Insert', 'Key', 'NewParagraph', 'Note', 'Paragraph'];
						}

						if (isset($pl['opt']['icon']) and in_array($pl['opt']['icon'], $iconsapp)) {
							$annot .= ' /Name /' . $pl['opt']['icon'];
						} elseif ($fileAttachment) {
							$annot .= ' /Name /PushPin';
						} else {
							$annot .= ' /Name /Note';
						}

						if (!$fileAttachment) {
							///Subj is PDF 1.5 spec.
							if (isset($pl['opt']['subj']) && !$this->PDFA && !$this->PDFX) {
								$annot .= ' /Subj ' . $this->_UTF16BEtextstring($pl['opt']['subj']);
							}
							if (!empty($pl['opt']['popup'])) {
								$annot .= ' /Open true';
								$annot .= ' /Popup ' . ($this->n + 1) . ' 0 R';
							} else {
								$annot .= ' /Open false';
							}
						}

						$annot .= ' /P ' . $pl['pageobj'] . ' 0 R';
						$annot .= '>>';
						$this->_out($annot);
						$this->_out('endobj');

						if ($fileAttachment) {
							$file = @file_get_contents($pl['opt']['file']);
							if (!$file) {
								throw new \Mpdf\MpdfException('mPDF Error: Cannot access file attachment - ' . $pl['opt']['file']);
							}
							$filestream = gzcompress($file);
							$this->_newobj();
							$this->_out('<</Type /EmbeddedFile');
							$this->_out('/Length ' . strlen($filestream));
							$this->_out('/Filter /FlateDecode');
							$this->_out('>>');
							$this->_putstream($filestream);
							$this->_out('endobj');
						} elseif (!empty($pl['opt']['popup'])) {
							$this->_newobj();
							$annot = '';
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][0])) {
								$x = $pl['opt']['popup'][0] * Mpdf::SCALE;
							} else {
								$x = $pl['x'] * Mpdf::SCALE;
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][1])) {
								$y = $hPt - ($pl['opt']['popup'][1] * Mpdf::SCALE);
							} else {
								$y = $hPt - ($pl['y'] * Mpdf::SCALE);
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][2])) {
								$w = $pl['opt']['popup'][2] * Mpdf::SCALE;
							} else {
								$w = 180;
							}
							if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][3])) {
								$h = $pl['opt']['popup'][3] * Mpdf::SCALE;
							} else {
								$h = 120;
							}
							$rect = sprintf('%.3F %.3F %.3F %.3F', $x, $y - $h, $x + $w, $y);
							$annot .= '<</Type /Annot /Subtype /Popup /Rect [' . $rect . ']';
							$annot .= ' /M ' . $this->_textstring('D:' . date('YmdHis'));
							if ($this->PDFA || $this->PDFX) {
								$annot .= ' /F 28';
							}
							$annot .= ' /Parent ' . ($this->n - 1) . ' 0 R';
							$annot .= '>>';
							$this->_out($annot);
							$this->_out('endobj');
						}
					}
				}

				/* -- END ANNOTATIONS -- */

				/* -- FORMS -- */
				// Active Forms
				if (count($this->form->forms) > 0) {
					$this->form->_putFormItems($n, $hPt);
				}
				/* -- END FORMS -- */
			}
		}

		/* -- FORMS -- */
		// Active Forms - Radio Button Group entries
		// Output Radio Button Group form entries (radio_on_obj_id already determined)
		if (count($this->form->form_radio_groups)) {
			$this->form->_putRadioItems($n);
		}
		/* -- END FORMS -- */
	}

	function _putfonts()
	{
		$nf = $this->n;
		foreach ($this->FontFiles as $fontkey => $info) {
			// TrueType embedded
			if (isset($info['type']) && $info['type'] == 'TTF' && !$info['sip'] && !$info['smp']) {
				$used = true;
				$asSubset = false;
				foreach ($this->fonts as $k => $f) {
					if (isset($f['fontkey']) && $f['fontkey'] == $fontkey && $f['type'] == 'TTF') {
						$used = $f['used'];
						if ($used) {
							$nChars = (ord($f['cw'][0]) << 8) + ord($f['cw'][1]);
							$usage = intval(count($f['subset']) * 100 / $nChars);
							$fsize = $info['length1'];
							// Always subset the very large TTF files
							if ($fsize > ($this->maxTTFFilesize * 1024)) {
								$asSubset = true;
							} elseif ($usage < $this->percentSubset) {
								$asSubset = true;
							}
						}
						if ($this->PDFA || $this->PDFX) {
							$asSubset = false;
						}
						$this->fonts[$k]['asSubset'] = $asSubset;
						break;
					}
				}
				if ($used && !$asSubset) {
					// Font file embedding
					$this->_newobj();
					$this->FontFiles[$fontkey]['n'] = $this->n;
					$originalsize = $info['length1'];
					if ($this->repackageTTF || $this->fonts[$fontkey]['TTCfontID'] > 0 || $this->fonts[$fontkey]['useOTL'] > 0) { // mPDF 5.7.1
						// First see if there is a cached compressed file
						if ($this->fontCache->has($fontkey . '.ps.z')) {
							$font = $this->fontCache->load($fontkey . '.ps.z');
							include $this->fontCache->tempFilename($fontkey . '.ps.php'); // sets $originalsize (of repackaged font)
						} else {
							$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
							$font = $ttf->repackageTTF($this->FontFiles[$fontkey]['ttffile'], $this->fonts[$fontkey]['TTCfontID'], $this->debugfonts, $this->fonts[$fontkey]['useOTL']); // mPDF 5.7.1

							$originalsize = strlen($font);
							$font = gzcompress($font);
							unset($ttf);

							$len = "<?php \n";
							$len .= '$originalsize=' . $originalsize . ";\n";

							$this->fontCache->binaryWrite($fontkey . '.ps.z', $font);
							$this->fontCache->write($fontkey . '.ps.php', $len);
						}
					} else {
						// First see if there is a cached compressed file
						if ($this->fontCache->has($fontkey . '.z')) {
							$font = $this->fontCache->load($fontkey . '.z', 'rb');
						} else {
							$font = file_get_contents($this->FontFiles[$fontkey]['ttffile']);
							$font = gzcompress($font);
							$this->fontCache->binaryWrite($fontkey . '.z', $font);
						}
					}

					$this->_out('<</Length ' . strlen($font));
					$this->_out('/Filter /FlateDecode');
					$this->_out('/Length1 ' . $originalsize);
					$this->_out('>>');
					$this->_putstream($font);
					$this->_out('endobj');
				}
			}
		}

		$nfonts = count($this->fonts);
		$fctr = 1;
		foreach ($this->fonts as $k => $font) {
			// Font objects
			$type = $font['type'];
			$name = $font['name'];
			if ((!isset($font['used']) || !$font['used']) && $type == 'TTF') {
				continue;
			}

			// @log Writing fonts

			if (isset($font['asSubset'])) {
				$asSubset = $font['asSubset'];
			} else {
				$asSubset = '';
			}
			/* -- CJK-FONTS -- */
			if ($type == 'Type0') {  // = Adobe CJK Fonts
				$this->fonts[$k]['n'] = $this->n + 1;
				$this->_newobj();
				$this->_out('<</Type /Font');
				$this->_putType0($font);
			} else { 			/* -- END CJK-FONTS -- */
				if ($type == 'core') {
					// Standard font
					$this->fonts[$k]['n'] = $this->n + 1;
					if ($this->PDFA || $this->PDFX) {
						throw new \Mpdf\MpdfException('Core fonts are not allowed in PDF/A1-b or PDFX/1-a files (Times, Helvetica, Courier etc.)');
					}
					$this->_newobj();
					$this->_out('<</Type /Font');
					$this->_out('/BaseFont /' . $name);
					$this->_out('/Subtype /Type1');
					if ($name != 'Symbol' && $name != 'ZapfDingbats') {
						$this->_out('/Encoding /WinAnsiEncoding');
					}
					$this->_out('>>');
					$this->_out('endobj');
				} // TrueType embedded SUBSETS for SIP (CJK extB containing Supplementary Ideographic Plane 2)
			// Or Unicode Plane 1 - Supplementary Multilingual Plane
				elseif ($type == 'TTF' && ($font['sip'] || $font['smp'])) {
					if (!$font['used']) {
						continue;
					}
					$ssfaid = "AA";
					$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
					for ($sfid = 0; $sfid < count($font['subsetfontids']); $sfid++) {
						$this->fonts[$k]['n'][$sfid] = $this->n + 1;  // NB an array for subset
						$subsetname = 'MPDF' . $ssfaid . '+' . $font['name'];
						$ssfaid++;

						/* For some strange reason a subset ($sfid > 0) containing less than 97 characters causes an error
						  so fill up the array */
						for ($j = count($font['subsets'][$sfid]); $j < 98; $j++) {
							$font['subsets'][$sfid][$j] = 0;
						}

						$subset = $font['subsets'][$sfid];
						unset($subset[0]);
						$ttfontstream = $ttf->makeSubsetSIP($font['ttffile'], $subset, $font['TTCfontID'], $this->debugfonts, $font['useOTL']); // mPDF 5.7.1
						$ttfontsize = strlen($ttfontstream);
						$fontstream = gzcompress($ttfontstream);
						$widthstring = '';
						$toUnistring = '';


						foreach ($font['subsets'][$sfid] as $cp => $u) {
							$w = $this->_getCharWidth($font['cw'], $u);
							if ($w !== false) {
								$widthstring .= $w . ' ';
							} else {
								$widthstring .= round($ttf->defaultWidth) . ' ';
							}
							if ($u > 65535) {
								$utf8 = chr(($u >> 18) + 240) . chr((($u >> 12) & 63) + 128) . chr((($u >> 6) & 63) + 128) . chr(($u & 63) + 128);
								$utf16 = mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
								$l1 = ord($utf16[0]);
								$h1 = ord($utf16[1]);
								$l2 = ord($utf16[2]);
								$h2 = ord($utf16[3]);
								$toUnistring .= sprintf("<%02s> <%02s%02s%02s%02s>\n", strtoupper(dechex($cp)), strtoupper(dechex($l1)), strtoupper(dechex($h1)), strtoupper(dechex($l2)), strtoupper(dechex($h2)));
							} else {
								$toUnistring .= sprintf("<%02s> <%04s>\n", strtoupper(dechex($cp)), strtoupper(dechex($u)));
							}
						}

						// Additional Type1 or TrueType font
						$this->_newobj();
						$this->_out('<</Type /Font');
						$this->_out('/BaseFont /' . $subsetname);
						$this->_out('/Subtype /TrueType');
						$this->_out('/FirstChar 0 /LastChar ' . (count($font['subsets'][$sfid]) - 1));
						$this->_out('/Widths ' . ($this->n + 1) . ' 0 R');
						$this->_out('/FontDescriptor ' . ($this->n + 2) . ' 0 R');
						$this->_out('/ToUnicode ' . ($this->n + 3) . ' 0 R');
						$this->_out('>>');
						$this->_out('endobj');

						// Widths
						$this->_newobj();
						$this->_out('[' . $widthstring . ']');
						$this->_out('endobj');

						// Descriptor
						$this->_newobj();
						$s = '<</Type /FontDescriptor /FontName /' . $subsetname . "\n";
						foreach ($font['desc'] as $kd => $v) {
							if ($kd == 'Flags') {
								$v = $v | 4;
								$v = $v & ~32;
							} // SYMBOLIC font flag
							$s.=' /' . $kd . ' ' . $v . "\n";
						}
						$s.='/FontFile2 ' . ($this->n + 2) . ' 0 R';
						$this->_out($s . '>>');
						$this->_out('endobj');

						// ToUnicode
						$this->_newobj();
						$toUni = "/CIDInit /ProcSet findresource begin\n";
						$toUni .= "12 dict begin\n";
						$toUni .= "begincmap\n";
						$toUni .= "/CIDSystemInfo\n";
						$toUni .= "<</Registry (Adobe)\n";
						$toUni .= "/Ordering (UCS)\n";
						$toUni .= "/Supplement 0\n";
						$toUni .= ">> def\n";
						$toUni .= "/CMapName /Adobe-Identity-UCS def\n";
						$toUni .= "/CMapType 2 def\n";
						$toUni .= "1 begincodespacerange\n";
						$toUni .= "<00> <FF>\n";
						// $toUni .= sprintf("<00> <%02s>\n", strtoupper(dechex(count($font['subsets'][$sfid])-1)));
						$toUni .= "endcodespacerange\n";
						$toUni .= count($font['subsets'][$sfid]) . " beginbfchar\n";
						$toUni .= $toUnistring;
						$toUni .= "endbfchar\n";
						$toUni .= "endcmap\n";
						$toUni .= "CMapName currentdict /CMap defineresource pop\n";
						$toUni .= "end\n";
						$toUni .= "end\n";
						$this->_out('<</Length ' . (strlen($toUni)) . '>>');
						$this->_putstream($toUni);
						$this->_out('endobj');

						// Font file
						$this->_newobj();
						$this->_out('<</Length ' . strlen($fontstream));
						$this->_out('/Filter /FlateDecode');
						$this->_out('/Length1 ' . $ttfontsize);
						$this->_out('>>');
						$this->_putstream($fontstream);
						$this->_out('endobj');
					} // foreach subset
					unset($ttf);
				} // TrueType embedded SUBSETS or FULL
				elseif ($type == 'TTF') {
					$this->fonts[$k]['n'] = $this->n + 1;
					if ($asSubset) {
						$ssfaid = "A";
						$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
						$fontname = 'MPDFA' . $ssfaid . '+' . $font['name'];
						$subset = $font['subset'];
						unset($subset[0]);
						$ttfontstream = $ttf->makeSubset($font['ttffile'], $subset, $font['TTCfontID'], $this->debugfonts, $font['useOTL']);
						$ttfontsize = strlen($ttfontstream);
						$fontstream = gzcompress($ttfontstream);
						$codeToGlyph = $ttf->codeToGlyph;
						unset($codeToGlyph[0]);
					} else {
						$fontname = $font['name'];
					}
					// Type0 Font
					// A composite font - a font composed of other fonts, organized hierarchically
					$this->_newobj();
					$this->_out('<</Type /Font');
					$this->_out('/Subtype /Type0');
					$this->_out('/BaseFont /' . $fontname . '');
					$this->_out('/Encoding /Identity-H');
					$this->_out('/DescendantFonts [' . ($this->n + 1) . ' 0 R]');
					$this->_out('/ToUnicode ' . ($this->n + 2) . ' 0 R');
					$this->_out('>>');
					$this->_out('endobj');

					// CIDFontType2
					// A CIDFont whose glyph descriptions are based on TrueType font technology
					$this->_newobj();
					$this->_out('<</Type /Font');
					$this->_out('/Subtype /CIDFontType2');
					$this->_out('/BaseFont /' . $fontname . '');
					$this->_out('/CIDSystemInfo ' . ($this->n + 2) . ' 0 R');
					$this->_out('/FontDescriptor ' . ($this->n + 3) . ' 0 R');
					if (isset($font['desc']['MissingWidth'])) {
						$this->_out('/DW ' . $font['desc']['MissingWidth'] . '');
					}

					if (!$asSubset && $this->fontCache->has($font['fontkey'] . '.cw')) {
						$w = $this->fontCache->load($font['fontkey'] . '.cw');
						$this->_out($w);
					} else {
						$this->_putTTfontwidths($font, $asSubset, ($asSubset ? $ttf->maxUni : 0));
					}

					$this->_out('/CIDToGIDMap ' . ($this->n + 4) . ' 0 R');
					$this->_out('>>');
					$this->_out('endobj');

					// ToUnicode
					$this->_newobj();
					$toUni = "/CIDInit /ProcSet findresource begin\n";
					$toUni .= "12 dict begin\n";
					$toUni .= "begincmap\n";
					$toUni .= "/CIDSystemInfo\n";
					$toUni .= "<</Registry (Adobe)\n";
					$toUni .= "/Ordering (UCS)\n";
					$toUni .= "/Supplement 0\n";
					$toUni .= ">> def\n";
					$toUni .= "/CMapName /Adobe-Identity-UCS def\n";
					$toUni .= "/CMapType 2 def\n";
					$toUni .= "1 begincodespacerange\n";
					$toUni .= "<0000> <FFFF>\n";
					$toUni .= "endcodespacerange\n";
					$toUni .= "1 beginbfrange\n";
					$toUni .= "<0000> <FFFF> <0000>\n";
					$toUni .= "endbfrange\n";
					$toUni .= "endcmap\n";
					$toUni .= "CMapName currentdict /CMap defineresource pop\n";
					$toUni .= "end\n";
					$toUni .= "end\n";
					$this->_out('<</Length ' . (strlen($toUni)) . '>>');
					$this->_putstream($toUni);
					$this->_out('endobj');


					// CIDSystemInfo dictionary
					$this->_newobj();
					$this->_out('<</Registry (Adobe)');
					$this->_out('/Ordering (UCS)');
					$this->_out('/Supplement 0');
					$this->_out('>>');
					$this->_out('endobj');

					// Font descriptor
					$this->_newobj();
					$this->_out('<</Type /FontDescriptor');
					$this->_out('/FontName /' . $fontname);
					foreach ($font['desc'] as $kd => $v) {
						if ($asSubset && $kd == 'Flags') {
							$v = $v | 4;
							$v = $v & ~32;
						} // SYMBOLIC font flag
						$this->_out(' /' . $kd . ' ' . $v);
					}
					if ($font['panose']) {
						$this->_out(' /Style << /Panose <' . $font['panose'] . '> >>');
					}
					if ($asSubset) {
						$this->_out('/FontFile2 ' . ($this->n + 2) . ' 0 R');
					} elseif ($font['fontkey']) {
						// obj ID of a stream containing a TrueType font program
						$this->_out('/FontFile2 ' . $this->FontFiles[$font['fontkey']]['n'] . ' 0 R');
					}
					$this->_out('>>');
					$this->_out('endobj');

					// Embed CIDToGIDMap
					// A specification of the mapping from CIDs to glyph indices
					if ($asSubset) {
						$cidtogidmap = '';
						$cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
						foreach ($codeToGlyph as $cc => $glyph) {
							$cidtogidmap[$cc * 2] = chr($glyph >> 8);
							$cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
						}
						$cidtogidmap = gzcompress($cidtogidmap);
					} else {
						// First see if there is a cached CIDToGIDMapfile
						$cidtogidmap = '';
						if ($this->fontCache->has($font['fontkey'] . '.cgm')) {
							$cidtogidmap = $this->fontCache->load($font['fontkey'] . '.cgm');
						} else {
							$ttf = new TTFontFile($this->fontCache, $this->fontDescriptor);
							$charToGlyph = $ttf->getCTG($font['ttffile'], $font['TTCfontID'], $this->debugfonts, $font['useOTL']);
							$cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
							foreach ($charToGlyph as $cc => $glyph) {
								$cidtogidmap[$cc * 2] = chr($glyph >> 8);
								$cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
							}
							unset($ttf);
							$cidtogidmap = gzcompress($cidtogidmap);
							$this->fontCache->binaryWrite($font['fontkey'] . '.cgm', $cidtogidmap);
						}
					}
					$this->_newobj();
					$this->_out('<</Length ' . strlen($cidtogidmap) . '');
					$this->_out('/Filter /FlateDecode');
					$this->_out('>>');
					$this->_putstream($cidtogidmap);
					$this->_out('endobj');

					// Font file
					if ($asSubset) {
						$this->_newobj();
						$this->_out('<</Length ' . strlen($fontstream));
						$this->_out('/Filter /FlateDecode');
						$this->_out('/Length1 ' . $ttfontsize);
						$this->_out('>>');
						$this->_putstream($fontstream);
						$this->_out('endobj');
						unset($ttf);
					}
				} else {
					throw new \Mpdf\MpdfException('Unsupported font type: ' . $type . ' (' . $name . ')');
				}
			}
		}
	}

	function _putTTfontwidths(&$font, $asSubset, $maxUni)
	{
		if ($asSubset && $this->fontCache->has($font['fontkey'] . '.cw127.php')) {
			include $this->fontCache->tempFilename($font['fontkey'] . '.cw127.php');
			$startcid = 128;
		} else {
			$rangeid = 0;
			$range = [];
			$prevcid = -2;
			$prevwidth = -1;
			$interval = false;
			$startcid = 1;
		}
		if ($asSubset) {
			$cwlen = $maxUni + 1;
		} else {
			$cwlen = (strlen($font['cw']) / 2);
		}

		// for each character
		for ($cid = $startcid; $cid < $cwlen; $cid++) {
			if ($cid == 128 && $asSubset && (!$this->fontCache->has($font['fontkey'] . '.cw127.php'))) {
				$cw127 = '<?php' . "\n";
				$cw127 .= '$rangeid=' . $rangeid . ";\n";
				$cw127 .= '$prevcid=' . $prevcid . ";\n";
				$cw127 .= '$prevwidth=' . $prevwidth . ";\n";
				if ($interval) {
					$cw127 .= '$interval=true' . ";\n";
				} else {
					$cw127 .= '$interval=false' . ";\n";
				}
				$cw127 .= '$range=' . var_export($range, true) . ";\n";
				$this->fontCache->write($font['fontkey'] . '.cw127.php', $cw127);
			}

			$character1 = isset($font['cw'][$cid * 2]) ? $font['cw'][$cid * 2] : '';
			$character2 = isset($font['cw'][$cid * 2 + 1]) ? $font['cw'][$cid * 2 + 1] : '';

			if ($character1 == "\00" && $character2 == "\00") {
				continue;
			}

			$width = (ord($character1) << 8) + ord($character2);

			if ($width == 65535) {
				$width = 0;
			}

			if ($asSubset && $cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
				continue;
			}

			if ($asSubset && $cid > 0xFFFF) {
				continue;
			} // mPDF 6

			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid == ($prevcid + 1)) {
					// consecutive CID
					if ($width == $prevwidth) {
						if ($width == $range[$rangeid][0]) {
							$range[$rangeid][] = $width;
						} else {
							array_pop($range[$rangeid]);
							// new range
							$rangeid = $prevcid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $prevwidth;
							$range[$rangeid][] = $width;
						}
						$interval = true;
						$range[$rangeid]['interval'] = true;
					} else {
						if ($interval) {
							// new range
							$rangeid = $cid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $width;
						} else {
							$range[$rangeid][] = $width;
						}
						$interval = false;
					}
				} else {
					// new range
					$rangeid = $cid;
					$range[$rangeid] = [];
					$range[$rangeid][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$w = $this->_putfontranges($range);
		$this->_out($w);
		if (!$asSubset) {
			$this->fontCache->binaryWrite($font['fontkey'] . '.cw', $w);
		}
	}

	function _putfontranges(&$range)
	{
		// optimize ranges
		$prevk = -1;
		$nextk = -1;
		$prevint = false;
		foreach ($range as $k => $ws) {
			$cws = count($ws);
			if (($k == $nextk) and ( !$prevint) and ( (!isset($ws['interval'])) or ( $cws < 4))) {
				if (isset($range[$k]['interval'])) {
					unset($range[$k]['interval']);
				}
				$range[$prevk] = array_merge($range[$prevk], $range[$k]);
				unset($range[$k]);
			} else {
				$prevk = $k;
			}
			$nextk = $k + $cws;
			if (isset($ws['interval'])) {
				if ($cws > 3) {
					$prevint = true;
				} else {
					$prevint = false;
				}
				unset($range[$k]['interval']);
				--$nextk;
			} else {
				$prevint = false;
			}
		}
		// output data
		$w = '';
		foreach ($range as $k => $ws) {
			if (count(array_count_values($ws)) == 1) {
				// interval mode is more compact
				$w .= ' ' . $k . ' ' . ($k + count($ws) - 1) . ' ' . $ws[0];
			} else {
				// range mode
				$w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
			}
		}
		return '/W [' . $w . ' ]';
	}

	function _putfontwidths(&$font, $cidoffset = 0)
	{
		ksort($font['cw']);
		unset($font['cw'][65535]);
		$rangeid = 0;
		$range = [];
		$prevcid = -2;
		$prevwidth = -1;
		$interval = false;
		// for each character
		foreach ($font['cw'] as $cid => $width) {
			$cid -= $cidoffset;
			if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
				if ($cid == ($prevcid + 1)) {
					// consecutive CID
					if ($width == $prevwidth) {
						if ($width == $range[$rangeid][0]) {
							$range[$rangeid][] = $width;
						} else {
							array_pop($range[$rangeid]);
							// new range
							$rangeid = $prevcid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $prevwidth;
							$range[$rangeid][] = $width;
						}
						$interval = true;
						$range[$rangeid]['interval'] = true;
					} else {
						if ($interval) {
							// new range
							$rangeid = $cid;
							$range[$rangeid] = [];
							$range[$rangeid][] = $width;
						} else {
							$range[$rangeid][] = $width;
						}
						$interval = false;
					}
				} else {
					// new range
					$rangeid = $cid;
					$range[$rangeid] = [];
					$range[$rangeid][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$this->_out($this->_putfontranges($range));
	}

	/* -- CJK-FONTS -- */

	// from class PDF_Chinese CJK EXTENSIONS
	function _putType0(&$font)
	{
		// Type0
		$this->_out('/Subtype /Type0');
		$this->_out('/BaseFont /' . $font['name'] . '-' . $font['CMap']);
		$this->_out('/Encoding /' . $font['CMap']);
		$this->_out('/DescendantFonts [' . ($this->n + 1) . ' 0 R]');
		$this->_out('>>');
		$this->_out('endobj');
		// CIDFont
		$this->_newobj();
		$this->_out('<</Type /Font');
		$this->_out('/Subtype /CIDFontType0');
		$this->_out('/BaseFont /' . $font['name']);

		$cidinfo = '/Registry ' . $this->_textstring('Adobe');
		$cidinfo .= ' /Ordering ' . $this->_textstring($font['registry']['ordering']);
		$cidinfo .= ' /Supplement ' . $font['registry']['supplement'];
		$this->_out('/CIDSystemInfo <<' . $cidinfo . '>>');

		$this->_out('/FontDescriptor ' . ($this->n + 1) . ' 0 R');
		if (isset($font['MissingWidth'])) {
			$this->_out('/DW ' . $font['MissingWidth'] . '');
		}
		$this->_putfontwidths($font, 31);
		$this->_out('>>');
		$this->_out('endobj');

		// Font descriptor
		$this->_newobj();
		$s = '<</Type /FontDescriptor /FontName /' . $font['name'];
		foreach ($font['desc'] as $k => $v) {
			if ($k != 'Style') {
				$s .= ' /' . $k . ' ' . $v . '';
			}
		}
		$this->_out($s . '>>');
		$this->_out('endobj');
	}

	/* -- END CJK-FONTS -- */

	function _putimages()
	{
		$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';

		foreach ($this->images as $file => $info) {

			$this->_newobj();

			$this->images[$file]['n'] = $this->n;

			$this->_out('<</Type /XObject');
			$this->_out('/Subtype /Image');
			$this->_out('/Width ' . $info['w']);
			$this->_out('/Height ' . $info['h']);

			if (isset($info['interpolation']) && $info['interpolation']) {
				$this->_out('/Interpolate true'); // mPDF 6 - image interpolation shall be performed by a conforming reader
			}

			if (isset($info['masked'])) {
				$this->_out('/SMask ' . ($this->n - 1) . ' 0 R');
			}

			// set color space
			$icc = false;
			if (isset($info['icc']) and ( $info['icc'] !== false)) {
				// ICC Colour Space
				$icc = true;
				$this->_out('/ColorSpace [/ICCBased ' . ($this->n + 1) . ' 0 R]');
			} elseif ($info['cs'] == 'Indexed') {
				if ($this->PDFX || ($this->PDFA && $this->restrictColorSpace == 3)) {
					throw new \Mpdf\MpdfException("PDFA1-b and PDFX/1-a files do not permit using mixed colour space (" . $file . ").");
				}
				$this->_out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
			} else {
				$this->_out('/ColorSpace /' . $info['cs']);
				if ($info['cs'] == 'DeviceCMYK') {
					if ($this->PDFA && $this->restrictColorSpace != 3) {
						throw new \Mpdf\MpdfException("PDFA1-b does not permit Images using mixed colour space (" . $file . ").");
					}
					if ($info['type'] == 'jpg') {
						$this->_out('/Decode [1 0 1 0 1 0 1 0]');
					}
				} elseif ($info['cs'] == 'DeviceRGB' && ($this->PDFX || ($this->PDFA && $this->restrictColorSpace == 3))) {
					throw new \Mpdf\MpdfException("PDFA1-b and PDFX/1-a files do not permit using mixed colour space (" . $file . ").");
				}
			}

			$this->_out('/BitsPerComponent ' . $info['bpc']);

			if (isset($info['f']) && $info['f']) {
				$this->_out('/Filter /' . $info['f']);
			}

			if (isset($info['parms'])) {
				$this->_out($info['parms']);
			}

			if (isset($info['trns']) and is_array($info['trns'])) {
				$trns = '';
				for ($i = 0; $i < count($info['trns']); $i++) {
					$trns.=$info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
				}
				$this->_out('/Mask [' . $trns . ']');
			}

			$this->_out('/Length ' . strlen($info['data']) . '>>');
			$this->_putstream($info['data']);

			unset($this->images[$file]['data']);

			$this->_out('endobj');

			if ($icc) { // ICC colour profile
				$this->_newobj();
				$icc = ($this->compress) ? gzcompress($info['icc']) : $info['icc'];
				$this->_out('<</N ' . $info['ch'] . ' ' . $filter . '/Length ' . strlen($icc) . '>>');
				$this->_putstream($icc);
				$this->_out('endobj');
			} elseif ($info['cs'] == 'Indexed') { // Palette
				$this->_newobj();
				$pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
				$this->_out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
				$this->_putstream($pal);
				$this->_out('endobj');
			}
		}
	}

	function _putinfo()
	{
		$this->_out('/Producer ' . $this->_UTF16BEtextstring('mPDF ' . $this->getVersionString()));
		if (!empty($this->title)) {
			$this->_out('/Title ' . $this->_UTF16BEtextstring($this->title));
		}
		if (!empty($this->subject)) {
			$this->_out('/Subject ' . $this->_UTF16BEtextstring($this->subject));
		}
		if (!empty($this->author)) {
			$this->_out('/Author ' . $this->_UTF16BEtextstring($this->author));
		}
		if (!empty($this->keywords)) {
			$this->_out('/Keywords ' . $this->_UTF16BEtextstring($this->keywords));
		}
		if (!empty($this->creator)) {
			$this->_out('/Creator ' . $this->_UTF16BEtextstring($this->creator));
		}
		foreach ($this->customProperties as $key => $value) {
			$this->_out('/' . $key . ' ' . $this->_UTF16BEtextstring($value));
		}

		$z = date('O'); // +0200
		$offset = substr($z, 0, 3) . "'" . substr($z, 3, 2) . "'";
		$this->_out('/CreationDate ' . $this->_textstring(date('YmdHis') . $offset));
		$this->_out('/ModDate ' . $this->_textstring(date('YmdHis') . $offset));
		if ($this->PDFX) {
			$this->_out('/Trapped/False');
			$this->_out('/GTS_PDFXVersion(PDF/X-1a:2003)');
		}
	}

	function _putmetadata()
	{
		$this->_newobj();
		$this->MetadataRoot = $this->n;
		$Producer = 'mPDF ' . self::VERSION;
		$z = date('O'); // +0200
		$offset = substr($z, 0, 3) . ':' . substr($z, 3, 2);
		$CreationDate = date('Y-m-d\TH:i:s') . $offset; // 2006-03-10T10:47:26-05:00 2006-06-19T09:05:17Z
		$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));


		$m = '<?xpacket begin="' . chr(239) . chr(187) . chr(191) . '" id="W5M0MpCehiHzreSzNTczkc9d"?>' . "\n"; // begin = FEFF BOM
		$m .= ' <x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="3.1-701">' . "\n";
		$m .= '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n";
		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">' . "\n";
		$m .= '    <pdf:Producer>' . $Producer . '</pdf:Producer>' . "\n";
		if (!empty($this->keywords)) {
			$m .= '    <pdf:Keywords>' . $this->keywords . '</pdf:Keywords>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmp="http://ns.adobe.com/xap/1.0/">' . "\n";
		$m .= '    <xmp:CreateDate>' . $CreationDate . '</xmp:CreateDate>' . "\n";
		$m .= '    <xmp:ModifyDate>' . $CreationDate . '</xmp:ModifyDate>' . "\n";
		$m .= '    <xmp:MetadataDate>' . $CreationDate . '</xmp:MetadataDate>' . "\n";
		if (!empty($this->creator)) {
			$m .= '    <xmp:CreatorTool>' . $this->creator . '</xmp:CreatorTool>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		// DC elements
		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
		$m .= '    <dc:format>application/pdf</dc:format>' . "\n";
		if (!empty($this->title)) {
			$m .= '    <dc:title>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . $this->title . '</rdf:li>
	 </rdf:Alt>
	</dc:title>' . "\n";
		}
		if (!empty($this->keywords)) {
			$m .= '    <dc:subject>
	 <rdf:Bag>
	  <rdf:li>' . $this->keywords . '</rdf:li>
	 </rdf:Bag>
	</dc:subject>' . "\n";
		}
		if (!empty($this->subject)) {
			$m .= '    <dc:description>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . $this->subject . '</rdf:li>
	 </rdf:Alt>
	</dc:description>' . "\n";
		}
		if (!empty($this->author)) {
			$m .= '    <dc:creator>
	 <rdf:Seq>
	  <rdf:li>' . $this->author . '</rdf:li>
	 </rdf:Seq>
	</dc:creator>' . "\n";
		}
		$m .= '   </rdf:Description>' . "\n";

		if (!empty($this->additionalXmpRdf)) {
			$m .= $this->additionalXmpRdf;
		}

		// This bit is specific to PDFX-1a
		if ($this->PDFX) {
			$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfx="http://ns.adobe.com/pdfx/1.3/" pdfx:Apag_PDFX_Checkup="1.3" pdfx:GTS_PDFXConformance="PDF/X-1a:2003" pdfx:GTS_PDFXVersion="PDF/X-1:2003"/>' . "\n";
		} // This bit is specific to PDFA-1b
		elseif ($this->PDFA) {

			if (strpos($this->PDFAversion, '-') === false) {
				throw new \Mpdf\MpdfException(sprintf('PDFA version (%s) is not valid. (Use: 1-B, 3-B, etc.)', $this->PDFAversion));
			}

			list($part, $conformance) = explode('-', strtoupper($this->PDFAversion));
			$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" >' . "\n";
			$m .= '    <pdfaid:part>' . $part . '</pdfaid:part>' . "\n";
			$m .= '    <pdfaid:conformance>' . $conformance . '</pdfaid:conformance>' . "\n";
			if ($part === '1' && $conformance === 'B') {
				$m .= '    <pdfaid:amd>2005</pdfaid:amd>' . "\n";
			}
			$m .= '   </rdf:Description>' . "\n";
		}

		$m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/">' . "\n";
		$m .= '    <xmpMM:DocumentID>uuid:' . $uuid . '</xmpMM:DocumentID>' . "\n";
		$m .= '   </rdf:Description>' . "\n";
		$m .= '  </rdf:RDF>' . "\n";
		$m .= ' </x:xmpmeta>' . "\n";
		$m .= str_repeat(str_repeat(' ', 100) . "\n", 20); // 2-4kB whitespace padding required
		$m .= '<?xpacket end="w"?>'; // "r" read only
		$this->_out('<</Type/Metadata/Subtype/XML/Length ' . strlen($m) . '>>');
		$this->_putstream($m);
		$this->_out('endobj');
	}

	function _putoutputintent()
	{
		$this->_newobj();
		$this->OutputIntentRoot = $this->n;
		$this->_out('<</Type /OutputIntent');

		$ICCProfile = preg_replace('/_/', ' ', basename($this->ICCProfile, '.icc'));

		if ($this->PDFA) {
			$this->_out('/S /GTS_PDFA1');
			if ($this->ICCProfile) {
				$this->_out('/Info (' . $ICCProfile . ')');
				$this->_out('/OutputConditionIdentifier (Custom)');
				$this->_out('/OutputCondition ()');
			} else {
				$this->_out('/Info (sRGB IEC61966-2.1)');
				$this->_out('/OutputConditionIdentifier (sRGB IEC61966-2.1)');
				$this->_out('/OutputCondition ()');
			}
			$this->_out('/DestOutputProfile ' . ($this->n + 1) . ' 0 R');
		} elseif ($this->PDFX) { // always a CMYK profile
			$this->_out('/S /GTS_PDFX');
			if ($this->ICCProfile) {
				$this->_out('/Info (' . $ICCProfile . ')');
				$this->_out('/OutputConditionIdentifier (Custom)');
				$this->_out('/OutputCondition ()');
				$this->_out('/DestOutputProfile ' . ($this->n + 1) . ' 0 R');
			} else {
				$this->_out('/Info (CGATS TR 001)');
				$this->_out('/OutputConditionIdentifier (CGATS TR 001)');
				$this->_out('/OutputCondition (CGATS TR 001 (SWOP))');
				$this->_out('/RegistryName (http://www.color.org)');
			}
		}
		$this->_out('>>');
		$this->_out('endobj');

		if ($this->PDFX && !$this->ICCProfile) {
			return;
		}

		$this->_newobj();

		if ($this->ICCProfile) {
			if (!file_exists($this->ICCProfile)) {
				throw new \Mpdf\MpdfException(sprintf('Unable to find ICC profile "%s"', $this->ICCProfile));
			}
			$s = file_get_contents($this->ICCProfile);
		} else {
			$s = file_get_contents(__DIR__ . '/../data/iccprofiles/sRGB_IEC61966-2-1.icc');
		}

		if ($this->compress) {
			$s = gzcompress($s);
		}

		$this->_out('<<');

		if ($this->PDFX || ($this->PDFA && $this->restrictColorSpace == 3)) {
			$this->_out('/N 4');
		} else {
			$this->_out('/N 3');
		}

		if ($this->compress) {
			$this->_out('/Filter /FlateDecode ');
		}

		$this->_out('/Length ' . strlen($s) . '>>');
		$this->_putstream($s);
		$this->_out('endobj');
	}

	function _putAssociatedFiles()
	{
		if (!function_exists('gzcompress')) {
			throw new \Mpdf\MpdfException('ext-zlib is required for compression of associated files');
		}

		// for each file, we create the spec object + the stream object
		foreach ($this->associatedFiles as $k => $file) {
			// spec
			$this->_newobj();
			$this->associatedFiles[$k]['_root'] = $this->n; // we store the root ref of object for future reference (e.g. /EmbeddedFiles catalog)
			$this->_out('<</F ' . $this->_textstring($file['name']));
			if ($file['description']) {
				$this->_out('/Desc ' . $this->_textstring($file['description']));
			}
			$this->_out('/Type /Filespec');
			$this->_out('/EF <<');
			$this->_out('/F ' . ($this->n + 1) . ' 0 R');
			$this->_out('/UF ' . ($this->n + 1) . ' 0 R');
			$this->_out('>>');
			if ($file['AFRelationship']) {
				$this->_out('/AFRelationship /' . $file['AFRelationship']);
			}
			$this->_out('/UF ' . $this->_textstring($file['name']));
			$this->_out('>>');
			$this->_out('endobj');

			$fileContent = null;
			if (isset($file['path'])) {
				$fileContent = @file_get_contents($file['path']);
			} elseif (isset($file['content'])) {
				$fileContent = $file['content'];
			}

			if (!$fileContent) {
				throw new \Mpdf\MpdfException(sprintf('Cannot access associated file - %s', $file['path']));
			}

			$filestream = gzcompress($fileContent);
			$this->_newobj();
			$this->_out('<</Type /EmbeddedFile');
			if ($file['mime']) {
				$this->_out('/Subtype /' . $this->_escapeName($file['mime']));
			}
			$this->_out('/Length '.strlen($filestream));
			$this->_out('/Filter /FlateDecode');
			if (isset($file['path'])) {
				$this->_out('/Params <</ModDate '.$this->_textstring('D:'.PdfDate::format(filemtime($file['path']))).' >>');
			} else {
				$this->_out('/Params <</ModDate '.$this->_textstring('D:'.PdfDate::format(time())).' >>');
			}

			$this->_out('>>');
			$this->_putstream($filestream);
			$this->_out('endobj');
		}

		// AF array
		$this->_newobj();
		$refs = [];
		foreach ($this->associatedFiles as $file) {
			array_push($refs, '' . $file['_root'] . ' 0 R');
		}
		$this->_out('[' . join(' ', $refs) . ']');
		$this->_out('endobj');
		$this->associatedFilesRoot = $this->n;
	}

	function _putcatalog()
	{
		$this->_out('/Type /Catalog');
		$this->_out('/Pages 1 0 R');
		if ($this->ZoomMode == 'fullpage') {
			$this->_out('/OpenAction [3 0 R /Fit]');
		} elseif ($this->ZoomMode == 'fullwidth') {
			$this->_out('/OpenAction [3 0 R /FitH null]');
		} elseif ($this->ZoomMode == 'real') {
			$this->_out('/OpenAction [3 0 R /XYZ null null 1]');
		} elseif (!is_string($this->ZoomMode)) {
			$this->_out('/OpenAction [3 0 R /XYZ null null ' . ($this->ZoomMode / 100) . ']');
		} else {
			$this->_out('/OpenAction [3 0 R /XYZ null null null]');
		}
		if ($this->LayoutMode == 'single') {
			$this->_out('/PageLayout /SinglePage');
		} elseif ($this->LayoutMode == 'continuous') {
			$this->_out('/PageLayout /OneColumn');
		} elseif ($this->LayoutMode == 'twoleft') {
			$this->_out('/PageLayout /TwoColumnLeft');
		} elseif ($this->LayoutMode == 'tworight') {
			$this->_out('/PageLayout /TwoColumnRight');
		} elseif ($this->LayoutMode == 'two') {
			if ($this->mirrorMargins) {
				$this->_out('/PageLayout /TwoColumnRight');
			} else {
				$this->_out('/PageLayout /TwoColumnLeft');
			}
		}

		// Bookmarks
		if (count($this->BMoutlines) > 0) {
			$this->_out('/Outlines ' . $this->OutlineRoot . ' 0 R');
			$this->_out('/PageMode /UseOutlines');
		}

		// Fullscreen
		if (is_int(strpos($this->DisplayPreferences, 'FullScreen'))) {
			$this->_out('/PageMode /FullScreen');
		}

		// Metadata
		if ($this->PDFA || $this->PDFX) {
			$this->_out('/Metadata ' . $this->MetadataRoot . ' 0 R');
		}

		// OutputIntents
		if ($this->PDFA || $this->PDFX || $this->ICCProfile) {
			$this->_out('/OutputIntents [' . $this->OutputIntentRoot . ' 0 R]');
		}

		// Associated files
		if ($this->associatedFilesRoot) {
			$this->_out('/AF '. $this->associatedFilesRoot .' 0 R');

			$names = [];
			foreach ($this->associatedFiles as $file) {
				array_push($names, $this->_textstring($file['name']) . ' ' . $file['_root'] . ' 0 R');
			}
			$this->_out('/Names << /EmbeddedFiles << /Names [' . join(' ', $names) .  '] >> >>');
		}

		// Forms
		if (count($this->form->forms) > 0) {
			$this->form->_putFormsCatalog();
		}

		if (isset($this->js)) {
			$this->_out('/Names << /JavaScript ' . ($this->n_js) . ' 0 R >> ');
		}

		if ($this->DisplayPreferences || $this->directionality == 'rtl' || $this->mirrorMargins) {
			$this->_out('/ViewerPreferences<<');
			if (is_int(strpos($this->DisplayPreferences, 'HideMenubar'))) {
				$this->_out('/HideMenubar true');
			}
			if (is_int(strpos($this->DisplayPreferences, 'HideToolbar'))) {
				$this->_out('/HideToolbar true');
			}
			if (is_int(strpos($this->DisplayPreferences, 'HideWindowUI'))) {
				$this->_out('/HideWindowUI true');
			}
			if (is_int(strpos($this->DisplayPreferences, 'DisplayDocTitle'))) {
				$this->_out('/DisplayDocTitle true');
			}
			if (is_int(strpos($this->DisplayPreferences, 'CenterWindow'))) {
				$this->_out('/CenterWindow true');
			}
			if (is_int(strpos($this->DisplayPreferences, 'FitWindow'))) {
				$this->_out('/FitWindow true');
			}
			///PrintScaling is PDF 1.6 spec.
			if (is_int(strpos($this->DisplayPreferences, 'NoPrintScaling')) && !$this->PDFA && !$this->PDFX) {
				$this->_out('/PrintScaling /None');
			}
			if ($this->directionality == 'rtl') {
				$this->_out('/Direction /R2L');
			}
			///Duplex is PDF 1.7 spec.
			if ($this->mirrorMargins && !$this->PDFA && !$this->PDFX) {
				// if ($this->DefOrientation=='P') $this->_out('/Duplex /DuplexFlipShortEdge');
				$this->_out('/Duplex /DuplexFlipLongEdge'); // PDF v1.7+
			}
			$this->_out('>>');
		}

		if ($this->open_layer_pane && ($this->hasOC || count($this->layers))) {
			$this->_out('/PageMode /UseOC');
		}

		if ($this->hasOC || count($this->layers)) {
			$p = $v = $h = $l = $loff = $lall = $as = '';
			if ($this->hasOC) {
				if (($this->hasOC & 1) == 1) {
					$p = $this->n_ocg_print . ' 0 R';
				}
				if (($this->hasOC & 2) == 2) {
					$v = $this->n_ocg_view . ' 0 R';
				}
				if (($this->hasOC & 4) == 4) {
					$h = $this->n_ocg_hidden . ' 0 R';
				}
				$as = "<</Event /Print /OCGs [$p $v $h] /Category [/Print]>> <</Event /View /OCGs [$p $v $h] /Category [/View]>>";
			}

			if (count($this->layers)) {
				foreach ($this->layers as $k => $layer) {
					if (strtolower($this->layerDetails[$k]['state']) == 'hidden') {
						$loff .= $layer['n'] . ' 0 R ';
					} else {
						$l .= $layer['n'] . ' 0 R ';
					}
					$lall .= $layer['n'] . ' 0 R ';
				}
			}
			$this->_out("/OCProperties <</OCGs [$p $v $h $lall] /D <</ON [$p $l] /OFF [$v $h $loff] ");
			$this->_out("/Order [$v $p $h $lall] ");
			if ($as) {
				$this->_out("/AS [$as] ");
			}
			$this->_out(">>>>");
		}
	}

	function _enddoc()
	{
		// @log Writing Headers & Footers

		$this->_puthtmlheaders();

		// @log Writing Pages

		// Remove references to unused fonts (usually default font)
		foreach ($this->fonts as $fk => $font) {
			if (isset($font['type']) && $font['type'] == 'TTF' && !$font['used']) {
				if ($font['sip'] || $font['smp']) {
					foreach ($font['subsetfontids'] as $k => $fid) {
						foreach ($this->pages as $pn => $page) {
							$this->pages[$pn] = preg_replace('/\s\/F' . $fid . ' \d[\d.]* Tf\s/is', ' ', $this->pages[$pn]);
						}
					}
				} else {
					foreach ($this->pages as $pn => $page) {
						$this->pages[$pn] = preg_replace('/\s\/F' . $font['i'] . ' \d[\d.]* Tf\s/is', ' ', $this->pages[$pn]);
					}
				}
			}
		}

		if (count($this->layers)) {
			foreach ($this->pages as $pn => $page) {
				preg_match_all('/\/OCZ-index \/ZI(\d+) BDC(.*?)(EMCZ)-index/is', $this->pages[$pn], $m1);
				preg_match_all('/\/OCBZ-index \/ZI(\d+) BDC(.*?)(EMCBZ)-index/is', $this->pages[$pn], $m2);
				preg_match_all('/\/OCGZ-index \/ZI(\d+) BDC(.*?)(EMCGZ)-index/is', $this->pages[$pn], $m3);
				$m = [];
				for ($i = 0; $i < 4; $i++) {
					$m[$i] = array_merge($m1[$i], $m2[$i], $m3[$i]);
				}
				if (count($m[0])) {
					$sortarr = [];
					for ($i = 0; $i < count($m[0]); $i++) {
						$key = $m[1][$i] * 2;
						if ($m[3][$i] == 'EMCZ') {
							$key +=2; // background first then gradient then normal
						} elseif ($m[3][$i] == 'EMCGZ') {
							$key +=1;
						}
						$sortarr[$i] = $key;
					}
					asort($sortarr);
					foreach ($sortarr as $i => $k) {
						$this->pages[$pn] = str_replace($m[0][$i], '', $this->pages[$pn]);
						$this->pages[$pn] .= "\n" . $m[0][$i] . "\n";
					}
					$this->pages[$pn] = preg_replace('/\/OC[BG]{0,1}Z-index \/ZI(\d+) BDC/is', '/OC /ZI\\1 BDC ', $this->pages[$pn]);
					$this->pages[$pn] = preg_replace('/EMC[BG]{0,1}Z-index/is', 'EMC', $this->pages[$pn]);
				}
			}
		}

		$this->_putpages();

		// @log Writing document resources

		$this->_putresources();
		// Info
		$this->_newobj();
		$this->InfoRoot = $this->n;
		$this->_out('<<');

		// @log Writing document info

		$this->_putinfo();
		$this->_out('>>');
		$this->_out('endobj');

		// METADATA
		if ($this->PDFA || $this->PDFX) {
			$this->_putmetadata();
		}

		// OUTPUTINTENT
		if ($this->PDFA || $this->PDFX || $this->ICCProfile) {
			$this->_putoutputintent();
		}

		// Associated files
		if ($this->associatedFiles) {
			$this->_putAssociatedFiles();
		}

		// Catalog
		$this->_newobj();
		$this->_out('<<');

		// @log Writing document catalog

		$this->_putcatalog();
		$this->_out('>>');
		$this->_out('endobj');

		// Cross-ref
		$o = strlen($this->buffer);
		$this->_out('xref');
		$this->_out('0 ' . ($this->n + 1));
		$this->_out('0000000000 65535 f ');

		for ($i = 1; $i <= $this->n; $i++) {
			$this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
		}

		// Trailer
		$this->_out('trailer');
		$this->_out('<<');
		$this->_puttrailer();
		$this->_out('>>');
		$this->_out('startxref');
		$this->_out($o);

		$this->buffer .= '%%EOF';
		$this->state = 3;

		// Imports
		if ($this->enableImports && count($this->parsers) > 0) {
			foreach ($this->parsers as $k => $_) {
				$this->parsers[$k]->closeFile();
				$this->parsers[$k] = null;
				unset($this->parsers[$k]);
			}
		}
	}

	function _beginpage(
		$orientation,
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
		if (!($pagesel && $this->page == 1 && (sprintf("%0.4f", $this->y) == sprintf("%0.4f", $this->tMargin)))) {
			$this->page++;
			$this->pages[$this->page] = '';
		}
		$this->state = 2;
		$resetHTMLHeadersrequired = false;

		if ($newformat) {
			$this->_setPageSize($newformat, $orientation);
		}

		/* -- CSS-PAGE -- */
		// Paged media (page-box)
		if ($pagesel || (isset($this->page_box['using']) && $this->page_box['using'])) {
			if ($pagesel || $this->page == 1) {
				$first = true;
			} else {
				$first = false;
			}
			if ($this->mirrorMargins && ($this->page % 2 == 0)) {
				$oddEven = 'E';
			} else {
				$oddEven = 'O';
			}
			if ($pagesel) {
				$psel = $pagesel;
			} elseif ($this->page_box['current']) {
				$psel = $this->page_box['current'];
			} else {
				$psel = '';
			}
			list($orientation, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $hname, $fname, $bg, $resetpagenum, $pagenumstyle, $suppress, $marks, $newformat) = $this->SetPagedMediaCSS($psel, $first, $oddEven);
			if ($this->mirrorMargins && ($this->page % 2 == 0)) {
				if ($hname) {
					$ehvalue = 1;
					$ehname = $hname;
				} else {
					$ehvalue = -1;
				}
				if ($fname) {
					$efvalue = 1;
					$efname = $fname;
				} else {
					$efvalue = -1;
				}
			} else {
				if ($hname) {
					$ohvalue = 1;
					$ohname = $hname;
				} else {
					$ohvalue = -1;
				}
				if ($fname) {
					$ofvalue = 1;
					$ofname = $fname;
				} else {
					$ofvalue = -1;
				}
			}
			if ($resetpagenum || $pagenumstyle || $suppress) {
				$this->PageNumSubstitutions[] = ['from' => ($this->page), 'reset' => $resetpagenum, 'type' => $pagenumstyle, 'suppress' => $suppress];
			}
			// PAGED MEDIA - CROP / CROSS MARKS from @PAGE
			$this->show_marks = $marks;

			// Background color
			if (isset($bg['BACKGROUND-COLOR'])) {
				$cor = $this->colorConverter->convert($bg['BACKGROUND-COLOR'], $this->PDFAXwarnings);
				if ($cor) {
					$this->bodyBackgroundColor = $cor;
				}
			} else {
				$this->bodyBackgroundColor = false;
			}

			/* -- BACKGROUNDS -- */
			if (isset($bg['BACKGROUND-GRADIENT'])) {
				$this->bodyBackgroundGradient = $bg['BACKGROUND-GRADIENT'];
			} else {
				$this->bodyBackgroundGradient = false;
			}

			// Tiling Patterns
			if (isset($bg['BACKGROUND-IMAGE']) && $bg['BACKGROUND-IMAGE']) {
				$ret = $this->SetBackground($bg, $this->pgwidth);
				if ($ret) {
					$this->bodyBackgroundImage = $ret;
				}
			} else {
				$this->bodyBackgroundImage = false;
			}
			/* -- END BACKGROUNDS -- */

			$this->page_box['current'] = $psel;
			$this->page_box['using'] = true;
		}
		/* -- END CSS-PAGE -- */

		// Page orientation
		if (!$orientation) {
			$orientation = $this->DefOrientation;
		} else {
			$orientation = strtoupper(substr($orientation, 0, 1));
			if ($orientation != $this->DefOrientation) {
				$this->OrientationChanges[$this->page] = true;
			}
		}

		if ($orientation != $this->CurOrientation || $newformat) {

			// Change orientation
			if ($orientation == 'P') {
				$this->wPt = $this->fwPt;
				$this->hPt = $this->fhPt;
				$this->w = $this->fw;
				$this->h = $this->fh;
				if (($this->forcePortraitHeaders || $this->forcePortraitMargins) && $this->DefOrientation == 'P') {
					$this->tMargin = $this->orig_tMargin;
					$this->bMargin = $this->orig_bMargin;
					$this->DeflMargin = $this->orig_lMargin;
					$this->DefrMargin = $this->orig_rMargin;
					$this->margin_header = $this->orig_hMargin;
					$this->margin_footer = $this->orig_fMargin;
				} else {
					$resetHTMLHeadersrequired = true;
				}
			} else {
				$this->wPt = $this->fhPt;
				$this->hPt = $this->fwPt;
				$this->w = $this->fh;
				$this->h = $this->fw;

				if (($this->forcePortraitHeaders || $this->forcePortraitMargins) && $this->DefOrientation == 'P') {
					$this->tMargin = $this->orig_lMargin;
					$this->bMargin = $this->orig_rMargin;
					$this->DeflMargin = $this->orig_bMargin;
					$this->DefrMargin = $this->orig_tMargin;
					$this->margin_header = $this->orig_hMargin;
					$this->margin_footer = $this->orig_fMargin;
				} else {
					$resetHTMLHeadersrequired = true;
				}
			}

			$this->CurOrientation = $orientation;
			$this->ResetMargins();
			$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
			$this->PageBreakTrigger = $this->h - $this->bMargin;
		}

		$this->pageDim[$this->page]['w'] = $this->w;
		$this->pageDim[$this->page]['h'] = $this->h;

		$this->pageDim[$this->page]['outer_width_LR'] = isset($this->page_box['outer_width_LR']) ? $this->page_box['outer_width_LR'] : 0;
		$this->pageDim[$this->page]['outer_width_TB'] = isset($this->page_box['outer_width_TB']) ? $this->page_box['outer_width_TB'] : 0;

		if (!isset($this->page_box['outer_width_LR']) && !isset($this->page_box['outer_width_TB'])) {
			$this->pageDim[$this->page]['bleedMargin'] = 0;
		} elseif ($this->bleedMargin <= $this->page_box['outer_width_LR'] && $this->bleedMargin <= $this->page_box['outer_width_TB']) {
			$this->pageDim[$this->page]['bleedMargin'] = $this->bleedMargin;
		} else {
			$this->pageDim[$this->page]['bleedMargin'] = min($this->page_box['outer_width_LR'], $this->page_box['outer_width_TB']) - 0.01;
		}

		// If Page Margins are re-defined
		// strlen()>0 is used to pick up (integer) 0, (string) '0', or set value
		if ((strlen($mgl) > 0 && $this->DeflMargin != $mgl) || (strlen($mgr) > 0 && $this->DefrMargin != $mgr) || (strlen($mgt) > 0 && $this->tMargin != $mgt) || (strlen($mgb) > 0 && $this->bMargin != $mgb) || (strlen($mgh) > 0 && $this->margin_header != $mgh) || (strlen($mgf) > 0 && $this->margin_footer != $mgf)) {

			if (strlen($mgl) > 0) {
				$this->DeflMargin = $mgl;
			}

			if (strlen($mgr) > 0) {
				$this->DefrMargin = $mgr;
			}

			if (strlen($mgt) > 0) {
				$this->tMargin = $mgt;
			}

			if (strlen($mgb) > 0) {
				$this->bMargin = $mgb;
			}

			if (strlen($mgh) > 0) {
				$this->margin_header = $mgh;
			}

			if (strlen($mgf) > 0) {
				$this->margin_footer = $mgf;
			}

			$this->ResetMargins();
			$this->SetAutoPageBreak($this->autoPageBreak, $this->bMargin);

			$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
			$resetHTMLHeadersrequired = true;
		}

		$this->ResetMargins();
		$this->pgwidth = $this->w - $this->lMargin - $this->rMargin;
		$this->SetAutoPageBreak($this->autoPageBreak, $this->bMargin);

		// Reset column top margin
		$this->y0 = $this->tMargin;

		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
		$this->FontFamily = '';

		// HEADERS AND FOOTERS	// mPDF 6
		if ($ohvalue < 0 || strtoupper($ohvalue) == 'OFF') {
			$this->HTMLHeader = '';
			$resetHTMLHeadersrequired = true;
		} elseif ($ohname && $ohvalue > 0) {
			if (preg_match('/^html_(.*)$/i', $ohname, $n)) {
				$name = $n[1];
			} else {
				$name = $ohname;
			}
			if (isset($this->pageHTMLheaders[$name])) {
				$this->HTMLHeader = $this->pageHTMLheaders[$name];
			} else {
				$this->HTMLHeader = '';
			}
			$resetHTMLHeadersrequired = true;
		}

		if ($ehvalue < 0 || strtoupper($ehvalue) == 'OFF') {
			$this->HTMLHeaderE = '';
			$resetHTMLHeadersrequired = true;
		} elseif ($ehname && $ehvalue > 0) {
			if (preg_match('/^html_(.*)$/i', $ehname, $n)) {
				$name = $n[1];
			} else {
				$name = $ehname;
			}
			if (isset($this->pageHTMLheaders[$name])) {
				$this->HTMLHeaderE = $this->pageHTMLheaders[$name];
			} else {
				$this->HTMLHeaderE = '';
			}
			$resetHTMLHeadersrequired = true;
		}

		if ($ofvalue < 0 || strtoupper($ofvalue) == 'OFF') {
			$this->HTMLFooter = '';
			$resetHTMLHeadersrequired = true;
		} elseif ($ofname && $ofvalue > 0) {
			if (preg_match('/^html_(.*)$/i', $ofname, $n)) {
				$name = $n[1];
			} else {
				$name = $ofname;
			}
			if (isset($this->pageHTMLfooters[$name])) {
				$this->HTMLFooter = $this->pageHTMLfooters[$name];
			} else {
				$this->HTMLFooter = '';
			}
			$resetHTMLHeadersrequired = true;
		}

		if ($efvalue < 0 || strtoupper($efvalue) == 'OFF') {
			$this->HTMLFooterE = '';
			$resetHTMLHeadersrequired = true;
		} elseif ($efname && $efvalue > 0) {
			if (preg_match('/^html_(.*)$/i', $efname, $n)) {
				$name = $n[1];
			} else {
				$name = $efname;
			}
			if (isset($this->pageHTMLfooters[$name])) {
				$this->HTMLFooterE = $this->pageHTMLfooters[$name];
			} else {
				$this->HTMLFooterE = '';
			}
			$resetHTMLHeadersrequired = true;
		}

		if ($resetHTMLHeadersrequired) {
			$this->SetHTMLHeader($this->HTMLHeader);
			$this->SetHTMLHeader($this->HTMLHeaderE, 'E');
			$this->SetHTMLFooter($this->HTMLFooter);
			$this->SetHTMLFooter($this->HTMLFooterE, 'E');
		}


		if (($this->mirrorMargins) && (($this->page) % 2 == 0)) { // EVEN
			$this->_setAutoHeaderHeight($this->HTMLHeaderE);
			$this->_setAutoFooterHeight($this->HTMLFooterE);
		} else { // ODD or DEFAULT
			$this->_setAutoHeaderHeight($this->HTMLHeader);
			$this->_setAutoFooterHeight($this->HTMLFooter);
		}

		// Reset column top margin
		$this->y0 = $this->tMargin;

		$this->x = $this->lMargin;
		$this->y = $this->tMargin;
	}

	// mPDF 6
	function _setAutoHeaderHeight(&$htmlh)
	{
		/* When the setAutoTopMargin option is set to pad/stretch, only apply auto header height when a header exists */
		if ($this->HTMLHeader === '' && $this->HTMLHeaderE === '') {
			return;
		}

		if ($this->setAutoTopMargin == 'pad') {
			if (isset($htmlh['h']) && $htmlh['h']) {
				$h = $htmlh['h'];
			} // 5.7.3
			else {
				$h = 0;
			}
			$this->tMargin = $this->margin_header + $h + $this->orig_tMargin;
		} elseif ($this->setAutoTopMargin == 'stretch') {
			if (isset($htmlh['h']) && $htmlh['h']) {
				$h = $htmlh['h'];
			} // 5.7.3
			else {
				$h = 0;
			}
			$this->tMargin = max($this->orig_tMargin, $this->margin_header + $h + $this->autoMarginPadding);
		}
	}

	// mPDF 6
	function _setAutoFooterHeight(&$htmlf)
	{
		/* When the setAutoTopMargin option is set to pad/stretch, only apply auto footer height when a footer exists */
		if ($this->HTMLFooter === '' && $this->HTMLFooterE === '') {
			return;
		}

		if ($this->setAutoBottomMargin == 'pad') {
			if (isset($htmlf['h']) && $htmlf['h']) {
				$h = $htmlf['h'];
			} // 5.7.3
			else {
				$h = 0;
			}
			$this->bMargin = $this->margin_footer + $h + $this->orig_bMargin;
			$this->PageBreakTrigger = $this->h - $this->bMargin;
		} elseif ($this->setAutoBottomMargin == 'stretch') {
			if (isset($htmlf['h']) && $htmlf['h']) {
				$h = $htmlf['h'];
			} // 5.7.3
			else {
				$h = 0;
			}
			$this->bMargin = max($this->orig_bMargin, $this->margin_footer + $h + $this->autoMarginPadding);
			$this->PageBreakTrigger = $this->h - $this->bMargin;
		}
	}

	function _endpage()
	{
		/* -- CSS-IMAGE-FLOAT -- */
		$this->printfloatbuffer();
		/* -- END CSS-IMAGE-FLOAT -- */

		if ($this->visibility != 'visible') {
			$this->SetVisibility('visible');
		}
		$this->EndLayer();
		// End of page contents
		$this->state = 1;
	}

	function _newobj($obj_id = false, $onlynewobj = false)
	{
		if (!$obj_id) {
			$obj_id = ++$this->n;
		}
		// Begin a new object
		if (!$onlynewobj) {
			$this->offsets[$obj_id] = strlen($this->buffer);
			$this->_out($obj_id . ' 0 obj');
			$this->_current_obj_id = $obj_id; // for later use with encryption
		}
	}

	function _dounderline($x, $y, $txt, $OTLdata = false, $textvar = 0)
	{
		// Now print line exactly where $y secifies - called from Text() and Cell() - adjust  position there
		// WORD SPACING
		$w = ($this->GetStringWidth($txt, false, $OTLdata, $textvar) * Mpdf::SCALE) + ($this->charspacing * mb_strlen($txt, $this->mb_enc)) + ( $this->ws * mb_substr_count($txt, ' ', $this->mb_enc));
		// Draw a line
		return sprintf('%.3F %.3F m %.3F %.3F l S', $x * Mpdf::SCALE, ($this->h - $y) * Mpdf::SCALE, ($x * Mpdf::SCALE) + $w, ($this->h - $y) * Mpdf::SCALE);
	}

	// ==============================================================
	// Moved outside WMF as also needed for SVG
	function _putformobjects()
	{
		foreach ($this->formobjects as $file => $info) {

			$this->_newobj();

			$this->formobjects[$file]['n'] = $this->n;

			$this->_out('<</Type /XObject');
			$this->_out('/Subtype /Form');
			$this->_out('/Group ' . ($this->n + 1) . ' 0 R');
			$this->_out('/BBox [' . $info['x'] . ' ' . $info['y'] . ' ' . ($info['w'] + $info['x']) . ' ' . ($info['h'] + $info['y']) . ']');

			if ($this->compress) {
				$this->_out('/Filter /FlateDecode');
			}

			$data = ($this->compress) ? gzcompress($info['data']) : $info['data'];
			$this->_out('/Length ' . strlen($data) . '>>');
			$this->_putstream($data);

			unset($this->formobjects[$file]['data']);

			$this->_out('endobj');

			// Required for SVG transparency (opacity) to work
			$this->_newobj();
			$this->_out('<</Type /Group');
			$this->_out('/S /Transparency');
			$this->_out('>>');
			$this->_out('endobj');
		}
	}

	function _freadint($f)
	{
		$i = ord(fread($f, 1)) << 24;
		$i += ord(fread($f, 1)) << 16;
		$i += ord(fread($f, 1)) << 8;
		$i += ord(fread($f, 1));

		return $i;
	}

	function _UTF16BEtextstring($s)
	{
		$s = $this->UTF8ToUTF16BE($s, true);
		if ($this->encrypted) {
			$s = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $s);
		}

		return '(' . $this->_escape($s) . ')';
	}

	function _textstring($s)
	{
		if ($this->encrypted) {
			$s = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $s);
		}

		return '(' . $this->_escape($s) . ')';
	}

	function _escape($s)
	{
		return strtr($s, [')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(13) => '\r']);
	}

	function _escapeName($s)
	{
		return strtr($s, array('/' => '#2F'));
	}

	function _putstream($s)
	{
		if ($this->encrypted) {
			$s = $this->protection->rc4($this->protection->objectKey($this->_current_obj_id), $s);
		}

		$this->_out('stream');
		$this->_out($s);
		$this->_out('endstream');
	}

	function _out($s, $ln = true)
	{
		if ($this->state == 2) {
			if ($this->bufferoutput) {
				$this->headerbuffer.= $s . "\n";
			} /* -- COLUMNS -- */ elseif (($this->ColActive) && !$this->processingHeader && !$this->processingFooter) {
				// Captures everything in buffer for columns; Almost everything is sent from fn. Cell() except:
				// Images sent from Image() or
				// later sent as _out($textto) in printbuffer
				// Line()
				if (preg_match('/q \d+\.\d\d+ 0 0 (\d+\.\d\d+) \d+\.\d\d+ \d+\.\d\d+ cm \/(I|FO)\d+ Do Q/', $s, $m)) { // Image data
					$h = ($m[1] / Mpdf::SCALE);
					// Update/overwrite the lowest bottom of printing y value for a column
					$this->ColDetails[$this->CurrCol]['bottom_margin'] = $this->y + $h;
				} /* -- TABLES -- */ elseif (preg_match('/\d+\.\d\d+ \d+\.\d\d+ \d+\.\d\d+ ([\-]{0,1}\d+\.\d\d+) re/', $s, $m) && $this->tableLevel > 0) { // Rect in table
					$h = ($m[1] / Mpdf::SCALE);
					// Update/overwrite the lowest bottom of printing y value for a column
					$this->ColDetails[$this->CurrCol]['bottom_margin'] = max($this->ColDetails[$this->CurrCol]['bottom_margin'], ($this->y + $h));
				} /* -- END TABLES -- */ else {  // Td Text Set in Cell()
					if (isset($this->ColDetails[$this->CurrCol]['bottom_margin'])) {
						$h = $this->ColDetails[$this->CurrCol]['bottom_margin'] - $this->y;
					} else {
						$h = 0;
					}
				}
				if ($h < 0) {
					$h = -$h;
				}
				$this->columnbuffer[] = [
					's' => $s, // Text string to output
					'col' => $this->CurrCol, // Column when printed
					'x' => $this->x, // x when printed
					'y' => $this->y, // this->y when printed (after column break)
					'h' => $h        // actual y at bottom when printed = y+h
				];
			} /* -- END COLUMNS -- */
			/* -- TABLES -- */ elseif ($this->table_rotate && !$this->processingHeader && !$this->processingFooter) {
				// Captures eveything in buffer for rotated tables;
				$this->tablebuffer .= $s . "\n";
			} /* -- END TABLES -- */ elseif ($this->kwt && !$this->processingHeader && !$this->processingFooter) {
				// Captures eveything in buffer for keep-with-table (h1-6);
				$this->kwt_buffer[] = [
					's' => $s, // Text string to output
					'x' => $this->x, // x when printed
					'y' => $this->y, // y when printed
				];
			} elseif (($this->keep_block_together) && !$this->processingHeader && !$this->processingFooter) {
				// do nothing
			} else {
				$this->pages[$this->page] .= $s . ($ln == true ? "\n" : '');
			}
		} else {
			$this->buffer .= $s . ($ln == true ? "\n" : '');
		}
	}

	function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
	{
		$h = $this->h;
		$this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', $x1 * Mpdf::SCALE, ($h - $y1) * Mpdf::SCALE, $x2 * Mpdf::SCALE, ($h - $y2) * Mpdf::SCALE, $x3 * Mpdf::SCALE, ($h - $y3) * Mpdf::SCALE));
	}

	// ====================================================


	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////


	function _getNormalLineheight($desc = false)
	{
		if (!$desc) {
			$desc = $this->CurrentFont['desc'];
		}
		if (!isset($desc['Leading'])) {
			$desc['Leading'] = 0;
		}
		if ($this->useFixedNormalLineHeight) {
			$lh = $this->normalLineheight;
		} elseif (isset($desc['Ascent']) && $desc['Ascent']) {
			$lh = ($this->adjustFontDescLineheight * ($desc['Ascent'] - $desc['Descent'] + $desc['Leading']) / 1000);
		} else {
			$lh = $this->normalLineheight;
		}
		return $lh;
	}

	function _computeLineheight($lh, $fs = '')
	{
		if ($this->shrin_k > 1) {
			$k = $this->shrin_k;
		} else {
			$k = 1;
		}
		if (!$fs) {
			$fs = $this->FontSize;
		}
		if ($lh == 'N') {
			$lh = $this->_getNormalLineheight();
		}
		if (preg_match('/mm/', $lh)) {
			return (((float) $lh) / $k); // convert to number
		} elseif ($lh > 0) {
			return ($fs * $lh);
		}
		return ($fs * $this->normalLineheight);
	}

	function _setLineYpos(&$fontsize, &$fontdesc, &$CSSlineheight, $blockYpos = false)
	{
		$ypos['glyphYorigin'] = 0;
		$ypos['baseline-shift'] = 0;
		$linegap = 0;
		$leading = 0;

		if (isset($fontdesc['Ascent']) && $fontdesc['Ascent'] && !$this->useFixedTextBaseline) {
			// Fontsize uses font metrics - this method seems to produce results compatible with browsers (except IE9)
			$ypos['boxtop'] = $fontdesc['Ascent'] / 1000 * $fontsize;
			$ypos['boxbottom'] = $fontdesc['Descent'] / 1000 * $fontsize;
			if (isset($fontdesc['Leading'])) {
				$linegap = $fontdesc['Leading'] / 1000 * $fontsize;
			}
		} // Default if not set - uses baselineC
		else {
			$ypos['boxtop'] = (0.5 + $this->baselineC) * $fontsize;
			$ypos['boxbottom'] = -(0.5 - $this->baselineC) * $fontsize;
		}
		$fontheight = $ypos['boxtop'] - $ypos['boxbottom'];

		if ($this->shrin_k > 1) {
			$shrin_k = $this->shrin_k;
		} else {
			$shrin_k = 1;
		}

		$leading = 0;
		if ($CSSlineheight == 'N') {
			$lh = $this->_getNormalLineheight($fontdesc);
			$lineheight = ($fontsize * $lh);
			$leading += $linegap; // specified in hhea or sTypo in OpenType tables
		} elseif (preg_match('/mm/', $CSSlineheight)) {
			$lineheight = (((float) $CSSlineheight) / $shrin_k); // convert to number
		} // ??? If lineheight is a factor e.g. 1.3  ?? use factor x 1em or ? use 'normal' lineheight * factor
		// Could depend on value for $text_height - a draft CSS value as set above for now
		elseif ($CSSlineheight > 0) {
			$lineheight = ($fontsize * $CSSlineheight);
		} else {
			$lineheight = ($fontsize * $this->normalLineheight);
		}

		// In general, calculate the "leading" - the difference between the fontheight and the lineheight
		// and add half to the top and half to the bottom. BUT
		// If an inline element has a font-size less than the block element, and the line-height is set as an em or % value
		// it will add too much leading below the font and expand the height of the line - so just use the block element exttop/extbottom:
		if (preg_match('/mm/', $CSSlineheight) && $ypos['boxtop'] < $blockYpos['boxtop'] && $ypos['boxbottom'] > $blockYpos['boxbottom']) {
			$ypos['exttop'] = $blockYpos['exttop'];
			$ypos['extbottom'] = $blockYpos['extbottom'];
		} else {
			$leading += ($lineheight - $fontheight);

			$ypos['exttop'] = $ypos['boxtop'] + $leading / 2;
			$ypos['extbottom'] = $ypos['boxbottom'] - $leading / 2;
		}


		// TEMP ONLY FOR DEBUGGING *********************************
		// $ypos['lineheight'] = $lineheight;
		// $ypos['fontheight'] = $fontheight;
		// $ypos['leading'] = $leading;

		return $ypos;
	}

	/* Called from WriteFlowingBlock() and finishFlowingBlock()
	  Determines the line hieght and glyph/writing position
	  for each element in the line to be written */

	function _setInlineBlockHeights(&$lineBox, &$stackHeight, &$content, &$font, $is_table)
	{
		if ($this->shrin_k > 1) {
			$shrin_k = $this->shrin_k;
		} else {
			$shrin_k = 1;
		}

		$ypos = [];
		$bordypos = [];
		$bgypos = [];

		if ($is_table) {
			// FOR TABLE
			$fontsize = $this->FontSize;
			$fontkey = $this->FontFamily . $this->FontStyle;
			$fontdesc = $this->fonts[$fontkey]['desc'];
			$CSSlineheight = $this->cellLineHeight;
			$line_stacking_strategy = $this->cellLineStackingStrategy; // inline-line-height [default] | block-line-height | max-height | grid-height
			$line_stacking_shift = $this->cellLineStackingShift;  // consider-shifts [default] | disregard-shifts
		} else {
			// FOR BLOCK FONT
			$fontsize = $this->blk[$this->blklvl]['InlineProperties']['size'];
			$fontkey = $this->blk[$this->blklvl]['InlineProperties']['family'] . $this->blk[$this->blklvl]['InlineProperties']['style'];
			$fontdesc = $this->fonts[$fontkey]['desc'];
			$CSSlineheight = $this->blk[$this->blklvl]['line_height'];
			// inline-line-height | block-line-height | max-height | grid-height
			$line_stacking_strategy = (isset($this->blk[$this->blklvl]['line_stacking_strategy']) ? $this->blk[$this->blklvl]['line_stacking_strategy'] : 'inline-line-height');
			// consider-shifts | disregard-shifts
			$line_stacking_shift = (isset($this->blk[$this->blklvl]['line_stacking_shift']) ? $this->blk[$this->blklvl]['line_stacking_shift'] : 'consider-shifts');
		}
		$boxLineHeight = $this->_computeLineheight($CSSlineheight, $fontsize);


		// First, set a "strut" using block font at index $lineBox[-1]
		$ypos[-1] = $this->_setLineYpos($fontsize, $fontdesc, $CSSlineheight);

		// for the block element - always taking the block EXTENDED progression including leading - which may be negative
		if ($line_stacking_strategy == 'block-line-height') {
			$topy = $ypos[-1]['exttop'];
			$bottomy = $ypos[-1]['extbottom'];
		} else {
			$topy = 0;
			$bottomy = 0;
		}

		// Get text-middle for aligning images/objects
		$midpoint = $ypos[-1]['boxtop'] - (($ypos[-1]['boxtop'] - $ypos[-1]['boxbottom']) / 2);

		// for images / inline objects / replaced elements
		$mta = 0; // Maximum top-aligned
		$mba = 0; // Maximum bottom-aligned
		foreach ($content as $k => $chunk) {
			if (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]['type'] == 'listmarker') {
				$ypos[$k] = $ypos[-1];
				// UPDATE Maximums
				if ($line_stacking_strategy == 'block-line-height' || $line_stacking_strategy == 'grid-height' || $line_stacking_strategy == 'max-height') { // don't include extended block progression of all inline elements
					if ($ypos[$k]['boxtop'] > $topy) {
						$topy = $ypos[$k]['boxtop'];
					}
					if ($ypos[$k]['boxbottom'] < $bottomy) {
						$bottomy = $ypos[$k]['boxbottom'];
					}
				} else {
					if ($ypos[$k]['exttop'] > $topy) {
						$topy = $ypos[$k]['exttop'];
					}
					if ($ypos[$k]['extbottom'] < $bottomy) {
						$bottomy = $ypos[$k]['extbottom'];
					}
				}
			} elseif (isset($this->objectbuffer[$k]) && $this->objectbuffer[$k]['type'] == 'dottab') { // mPDF 6 DOTTAB
				$fontsize = $font[$k]['size'];
				$fontdesc = $font[$k]['curr']['desc'];
				$lh = 1;
				$ypos[$k] = $this->_setLineYpos($fontsize, $fontdesc, $lh, $ypos[-1]); // Lineheight=1 fixed
			} elseif (isset($this->objectbuffer[$k])) {
				$oh = $this->objectbuffer[$k]['OUTER-HEIGHT'];
				$va = $this->objectbuffer[$k]['vertical-align'];

				if ($va == 'BS') { //  (BASELINE default)
					if ($oh > $topy) {
						$topy = $oh;
					}
				} elseif ($va == 'M') {
					if (($midpoint + $oh / 2) > $topy) {
						$topy = $midpoint + $oh / 2;
					}
					if (($midpoint - $oh / 2) < $bottomy) {
						$bottomy = $midpoint - $oh / 2;
					}
				} elseif ($va == 'TT') {
					if (($ypos[-1]['boxtop'] - $oh) < $bottomy) {
						$bottomy = $ypos[-1]['boxtop'] - $oh;
						$topy = max($topy, $ypos[-1]['boxtop']);
					}
				} elseif ($va == 'TB') {
					if (($ypos[-1]['boxbottom'] + $oh) > $topy) {
						$topy = $ypos[-1]['boxbottom'] + $oh;
						$bottomy = min($bottomy, $ypos[-1]['boxbottom']);
					}
				} elseif ($va == 'T') {
					if ($oh > $mta) {
						$mta = $oh;
					}
				} elseif ($va == 'B') {
					if ($oh > $mba) {
						$mba = $oh;
					}
				}
			} elseif ($content[$k] || $content[$k] === '0') {
				// FOR FLOWING BLOCK
				$fontsize = $font[$k]['size'];
				$fontdesc = $font[$k]['curr']['desc'];
				// In future could set CSS line-height from inline elements; for now, use block level:
				$ypos[$k] = $this->_setLineYpos($fontsize, $fontdesc, $CSSlineheight, $ypos[-1]);

				if (isset($font[$k]['textparam']['text-baseline']) && $font[$k]['textparam']['text-baseline'] != 0) {
					$ypos[$k]['baseline-shift'] = $font[$k]['textparam']['text-baseline'];
				}

				// DO ALIGNMENT FOR BASELINES *******************
				// Until most fonts have OpenType BASE tables, this won't work
				// $ypos[$k] compared to $ypos[-1] or $ypos[$k-1] using $dominant_baseline and $baseline_table
				// UPDATE Maximums
				if ($line_stacking_strategy == 'block-line-height' || $line_stacking_strategy == 'grid-height' || $line_stacking_strategy == 'max-height') { // don't include extended block progression of all inline elements
					if ($line_stacking_shift == 'disregard-shifts') {
						if ($ypos[$k]['boxtop'] > $topy) {
							$topy = $ypos[$k]['boxtop'];
						}
						if ($ypos[$k]['boxbottom'] < $bottomy) {
							$bottomy = $ypos[$k]['boxbottom'];
						}
					} else {
						if (($ypos[$k]['boxtop'] + $ypos[$k]['baseline-shift']) > $topy) {
							$topy = $ypos[$k]['boxtop'] + $ypos[$k]['baseline-shift'];
						}
						if (($ypos[$k]['boxbottom'] + $ypos[$k]['baseline-shift']) < $bottomy) {
							$bottomy = $ypos[$k]['boxbottom'] + $ypos[$k]['baseline-shift'];
						}
					}
				} else {
					if ($line_stacking_shift == 'disregard-shifts') {
						if ($ypos[$k]['exttop'] > $topy) {
							$topy = $ypos[$k]['exttop'];
						}
						if ($ypos[$k]['extbottom'] < $bottomy) {
							$bottomy = $ypos[$k]['extbottom'];
						}
					} else {
						if (($ypos[$k]['exttop'] + $ypos[$k]['baseline-shift']) > $topy) {
							$topy = $ypos[$k]['exttop'] + $ypos[$k]['baseline-shift'];
						}
						if (($ypos[$k]['extbottom'] + $ypos[$k]['baseline-shift']) < $bottomy) {
							$bottomy = $ypos[$k]['extbottom'] + $ypos[$k]['baseline-shift'];
						}
					}
				}

				// If BORDER set on inline element
				if (isset($font[$k]['bord']) && $font[$k]['bord']) {
					$bordfontsize = $font[$k]['textparam']['bord-decoration']['fontsize'] / $shrin_k;
					$bordfontkey = $font[$k]['textparam']['bord-decoration']['fontkey'];
					if ($bordfontkey != $fontkey || $bordfontsize != $fontsize || isset($font[$k]['textparam']['bord-decoration']['baseline'])) {
						$bordfontdesc = $this->fonts[$bordfontkey]['desc'];
						$bordypos[$k] = $this->_setLineYpos($bordfontsize, $bordfontdesc, $CSSlineheight, $ypos[-1]);
						if (isset($font[$k]['textparam']['bord-decoration']['baseline']) && $font[$k]['textparam']['bord-decoration']['baseline'] != 0) {
							$bordypos[$k]['baseline-shift'] = $font[$k]['textparam']['bord-decoration']['baseline'] / $shrin_k;
						}
					}
				}
				// If BACKGROUND set on inline element
				if (isset($font[$k]['spanbgcolor']) && $font[$k]['spanbgcolor']) {
					$bgfontsize = $font[$k]['textparam']['bg-decoration']['fontsize'] / $shrin_k;
					$bgfontkey = $font[$k]['textparam']['bg-decoration']['fontkey'];
					if ($bgfontkey != $fontkey || $bgfontsize != $fontsize || isset($font[$k]['textparam']['bg-decoration']['baseline'])) {
						$bgfontdesc = $this->fonts[$bgfontkey]['desc'];
						$bgypos[$k] = $this->_setLineYpos($bgfontsize, $bgfontdesc, $CSSlineheight, $ypos[-1]);
						if (isset($font[$k]['textparam']['bg-decoration']['baseline']) && $font[$k]['textparam']['bg-decoration']['baseline'] != 0) {
							$bgypos[$k]['baseline-shift'] = $font[$k]['textparam']['bg-decoration']['baseline'] / $shrin_k;
						}
					}
				}
			}
		}


		// TOP or BOTTOM aligned images
		if ($mta > ($topy - $bottomy)) {
			if (($topy - $mta) < $bottomy) {
				$bottomy = $topy - $mta;
			}
		}
		if ($mba > ($topy - $bottomy)) {
			if (($bottomy + $mba) > $topy) {
				$topy = $bottomy + $mba;
			}
		}

		if ($line_stacking_strategy == 'block-line-height') { // fixed height set by block element (whether present or not)
			$topy = $ypos[-1]['exttop'];
			$bottomy = $ypos[-1]['extbottom'];
		}

		$inclusiveHeight = $topy - $bottomy;

		// SET $stackHeight taking note of line_stacking_strategy
		// NB inclusive height already takes account of need to consider block progression height (excludes leading set by lineheight)
		// or extended block progression height (includes leading set by lineheight)
		if ($line_stacking_strategy == 'block-line-height') { // fixed = extended block progression height of block element
			$stackHeight = $boxLineHeight;
		} elseif ($line_stacking_strategy == 'max-height') { // smallest height which includes extended block progression height of block element
			// and block progression heights of inline elements (NOT extended)
			$stackHeight = $inclusiveHeight;
		} elseif ($line_stacking_strategy == 'grid-height') { // smallest multiple of block element lineheight to include
			// block progression heights of inline elements (NOT extended)
			$stackHeight = $boxLineHeight;
			while ($stackHeight < $inclusiveHeight) {
				$stackHeight += $boxLineHeight;
			}
		} else { // 'inline-line-height' = default		// smallest height which includes extended block progression height of block element
			// AND extended block progression heights of inline elements
			$stackHeight = $inclusiveHeight;
		}

		$diff = $stackHeight - $inclusiveHeight;
		$topy += $diff / 2;
		$bottomy -= $diff / 2;

		// ADJUST $ypos => lineBox using $stackHeight; lineBox are all offsets from the top of stackHeight in mm
		// and SET IMAGE OFFSETS
		$lineBox[-1]['boxtop'] = $topy - $ypos[-1]['boxtop'];
		$lineBox[-1]['boxbottom'] = $topy - $ypos[-1]['boxbottom'];
		// $lineBox[-1]['exttop'] = $topy - $ypos[-1]['exttop'];
		// $lineBox[-1]['extbottom'] = $topy - $ypos[-1]['extbottom'];
		$lineBox[-1]['glyphYorigin'] = $topy - $ypos[-1]['glyphYorigin'];
		$lineBox[-1]['baseline-shift'] = $ypos[-1]['baseline-shift'];

		$midpoint = $lineBox[-1]['boxbottom'] - (($lineBox[-1]['boxbottom'] - $lineBox[-1]['boxtop']) / 2);

		foreach ($content as $k => $chunk) {
			if (isset($this->objectbuffer[$k])) {
				$oh = $this->objectbuffer[$k]['OUTER-HEIGHT'];
				// LIST MARKERS
				if ($this->objectbuffer[$k]['type'] == 'listmarker') {
					$oh = $fontsize;
				} elseif ($this->objectbuffer[$k]['type'] == 'dottab') { // mPDF 6 DOTTAB
					$oh = $font[$k]['size']; // == $this->objectbuffer[$k]['fontsize']/Mpdf::SCALE;
					$lineBox[$k]['boxtop'] = $topy - $ypos[$k]['boxtop'];
					$lineBox[$k]['boxbottom'] = $topy - $ypos[$k]['boxbottom'];
					$lineBox[$k]['glyphYorigin'] = $topy - $ypos[$k]['glyphYorigin'];
					$lineBox[$k]['baseline-shift'] = 0;
					// continue;
				}
				$va = $this->objectbuffer[$k]['vertical-align']; // = $objattr['vertical-align'] = set as M,T,B,S

				if ($va == 'BS') { //  (BASELINE default)
					$lineBox[$k]['top'] = $lineBox[-1]['glyphYorigin'] - $oh;
				} elseif ($va == 'M') {
					$lineBox[$k]['top'] = $midpoint - $oh / 2;
				} elseif ($va == 'TT') {
					$lineBox[$k]['top'] = $lineBox[-1]['boxtop'];
				} elseif ($va == 'TB') {
					$lineBox[$k]['top'] = $lineBox[-1]['boxbottom'] - $oh;
				} elseif ($va == 'T') {
					$lineBox[$k]['top'] = 0;
				} elseif ($va == 'B') {
					$lineBox[$k]['top'] = $stackHeight - $oh;
				}
			} elseif ($content[$k] || $content[$k] === '0') {
				$lineBox[$k]['boxtop'] = $topy - $ypos[$k]['boxtop'];
				$lineBox[$k]['boxbottom'] = $topy - $ypos[$k]['boxbottom'];
				// $lineBox[$k]['exttop'] = $topy - $ypos[$k]['exttop'];
				// $lineBox[$k]['extbottom'] = $topy - $ypos[$k]['extbottom'];
				$lineBox[$k]['glyphYorigin'] = $topy - $ypos[$k]['glyphYorigin'];
				$lineBox[$k]['baseline-shift'] = $ypos[$k]['baseline-shift'];
				if (isset($bordypos[$k]['boxtop'])) {
					$lineBox[$k]['border-boxtop'] = $topy - $bordypos[$k]['boxtop'];
					$lineBox[$k]['border-boxbottom'] = $topy - $bordypos[$k]['boxbottom'];
					$lineBox[$k]['border-baseline-shift'] = $bordypos[$k]['baseline-shift'];
				}
				if (isset($bgypos[$k]['boxtop'])) {
					$lineBox[$k]['background-boxtop'] = $topy - $bgypos[$k]['boxtop'];
					$lineBox[$k]['background-boxbottom'] = $topy - $bgypos[$k]['boxbottom'];
					$lineBox[$k]['background-baseline-shift'] = $bgypos[$k]['baseline-shift'];
				}
			}
		}
	}

	// mPDF 6
	function _getStyledNumber($ppgno, $type, $listmarker = false)
	{
		if ($listmarker) {
			$reverse = true; // Reverse RTL numerals (Hebrew) when using for list
			$checkfont = true; // Using list - font is set, so check if character is available
		} else {
			$reverse = false; // For pagenumbers, RTL numerals (Hebrew) will get reversed later by bidi
			$checkfont = false; // For pagenumbers - font is not set, so no check
		}

		$decToAlpha = new Conversion\DecToAlpha();
		$decToCjk = new Conversion\DecToCjk();
		$decToHebrew = new Conversion\DecToHebrew();
		$decToRoman = new Conversion\DecToRoman();
		$decToOther = new Conversion\DecToOther($this);

		$lowertype = strtolower($type);

		if ($lowertype == 'upper-latin' || $lowertype == 'upper-alpha' || $type == 'A') {

			$ppgno = $decToAlpha->convert($ppgno, true);

		} elseif ($lowertype == 'lower-latin' || $lowertype == 'lower-alpha' || $type == 'a') {

			$ppgno = $decToAlpha->convert($ppgno, false);

		} elseif ($lowertype == 'upper-roman' || $type == 'I') {

			$ppgno = $decToRoman->convert($ppgno, true);

		} elseif ($lowertype == 'lower-roman' || $type == 'i') {

			$ppgno = $decToRoman->convert($ppgno, false);

		} elseif ($lowertype == 'hebrew') {

			$ppgno = $decToHebrew->convert($ppgno, $reverse);

		} elseif (preg_match('/(arabic-indic|bengali|devanagari|gujarati|gurmukhi|kannada|malayalam|oriya|persian|tamil|telugu|thai|urdu|cambodian|khmer|lao)/i', $lowertype, $m)) {

			$cp = $decToOther->getCodePage($m[1]);
			$ppgno = $decToOther->convert($ppgno, $cp, $checkfont);

		} elseif ($lowertype == 'cjk-decimal') {

			$ppgno = $decToCjk->convert($ppgno);

		}

		return $ppgno;
	}

	function _getHtmlHeight($html)
	{
		$save_state = $this->state;
		if ($this->state == 0) {
			$this->AddPage($this->CurOrientation);
		}
		$this->state = 2;
		$this->Reset();
		$this->pageoutput[$this->page] = [];
		$save_x = $this->x;
		$save_y = $this->y;
		$this->x = $this->lMargin;
		$this->y = $this->margin_header;
		$html = str_replace('{PAGENO}', $this->pagenumPrefix . $this->docPageNum($this->page) . $this->pagenumSuffix, $html);
		$html = str_replace($this->aliasNbPgGp, $this->nbpgPrefix . $this->docPageNumTotal($this->page) . $this->nbpgSuffix, $html);
		$html = str_replace($this->aliasNbPg, $this->page, $html);
		$html = preg_replace_callback('/\{DATE\s+(.*?)\}/', [$this, 'date_callback'], $html); // mPDF 5.7
		$this->HTMLheaderPageLinks = [];
		$this->HTMLheaderPageAnnots = [];
		$this->HTMLheaderPageForms = [];
		$savepb = $this->pageBackgrounds;
		$this->writingHTMLheader = true;
		$this->WriteHTML($html, 4); // parameter 4 saves output to $this->headerbuffer
		$this->writingHTMLheader = false;
		$h = ($this->y - $this->margin_header);
		$this->Reset();
		// mPDF 5.7.2 - Clear in case Float used in Header/Footer
		$this->blk[0]['blockContext'] = 0;
		$this->blk[0]['float_endpos'] = 0;

		$this->pageoutput[$this->page] = [];
		$this->headerbuffer = '';
		$this->pageBackgrounds = $savepb;
		$this->x = $save_x;
		$this->y = $save_y;
		$this->state = $save_state;
		if ($save_state == 0) {
			unset($this->pages[1]);
			$this->page = 0;
		}
		return $h;
	}

	// mPDF 6
	function _shareHeaderFooterWidth($cl, $cc, $cr)
	{
	// mPDF 6
		$l = mb_strlen($cl, 'UTF-8');
		$c = mb_strlen($cc, 'UTF-8');
		$r = mb_strlen($cr, 'UTF-8');
		$s = max($l, $r);
		$tw = $c + 2 * $s;
		if ($tw > 0) {
			return [intval($s * 100 / $tw), intval($c * 100 / $tw), intval($s * 100 / $tw)];
		} else {
			return [33, 33, 33];
		}
	}

	// mPDF 6
	// Create an HTML header/footer from array (non-HTML header/footer)
	function _createHTMLheaderFooter($arr, $hf)
	{
		$lContent = (isset($arr['L']['content']) ? $arr['L']['content'] : '');
		$cContent = (isset($arr['C']['content']) ? $arr['C']['content'] : '');
		$rContent = (isset($arr['R']['content']) ? $arr['R']['content'] : '');
		list($lw, $cw, $rw) = $this->_shareHeaderFooterWidth($lContent, $cContent, $rContent);
		if ($hf == 'H') {
			$valign = 'bottom';
			$vpadding = '0 0 ' . $this->header_line_spacing . 'em 0';
		} else {
			$valign = 'top';
			$vpadding = '' . $this->footer_line_spacing . 'em 0 0 0';
		}
		if ($this->directionality == 'rtl') { // table columns get reversed so need different text-alignment
			$talignL = 'right';
			$talignR = 'left';
		} else {
			$talignL = 'left';
			$talignR = 'right';
		}
		$html = '<table width="100%" style="border-collapse: collapse; margin: 0; vertical-align: ' . $valign . '; color: #000000; ';
		if (isset($arr['line']) && $arr['line']) {
			$html .= ' border-' . $valign . ': 0.1mm solid #000000;';
		}
		$html .= '">';
		$html .= '<tr>';
		$html .= '<td width="' . $lw . '%" style="padding: ' . $vpadding . '; text-align: ' . $talignL . '; ';
		if (isset($arr['L']['font-family'])) {
			$html .= ' font-family: ' . $arr['L']['font-family'] . ';';
		}
		if (isset($arr['L']['color'])) {
			$html .= ' color: ' . $arr['L']['color'] . ';';
		}
		if (isset($arr['L']['font-size'])) {
			$html .= ' font-size: ' . $arr['L']['font-size'] . 'pt;';
		}
		if (isset($arr['L']['font-style'])) {
			if ($arr['L']['font-style'] == 'B' || $arr['L']['font-style'] == 'BI') {
				$html .= ' font-weight: bold;';
			}
			if ($arr['L']['font-style'] == 'I' || $arr['L']['font-style'] == 'BI') {
				$html .= ' font-style: italic;';
			}
		}
		$html .= '">' . $lContent . '</td>';
		$html .= '<td width="' . $cw . '%" style="padding: ' . $vpadding . '; text-align: center; ';
		if (isset($arr['C']['font-family'])) {
			$html .= ' font-family: ' . $arr['C']['font-family'] . ';';
		}
		if (isset($arr['C']['color'])) {
			$html .= ' color: ' . $arr['C']['color'] . ';';
		}
		if (isset($arr['C']['font-size'])) {
			$html .= ' font-size: ' . $arr['L']['font-size'] . 'pt;';
		}
		if (isset($arr['C']['font-style'])) {
			if ($arr['C']['font-style'] == 'B' || $arr['C']['font-style'] == 'BI') {
				$html .= ' font-weight: bold;';
			}
			if ($arr['C']['font-style'] == 'I' || $arr['C']['font-style'] == 'BI') {
				$html .= ' font-style: italic;';
			}
		}
		$html .= '">' . $cContent . '</td>';
		$html .= '<td width="' . $rw . '%" style="padding: ' . $vpadding . '; text-align: ' . $talignR . '; ';
		if (isset($arr['R']['font-family'])) {
			$html .= ' font-family: ' . $arr['R']['font-family'] . ';';
		}
		if (isset($arr['R']['color'])) {
			$html .= ' color: ' . $arr['R']['color'] . ';';
		}
		if (isset($arr['R']['font-size'])) {
			$html .= ' font-size: ' . $arr['R']['font-size'] . 'pt;';
		}
		if (isset($arr['R']['font-style'])) {
			if ($arr['R']['font-style'] == 'B' || $arr['R']['font-style'] == 'BI') {
				$html .= ' font-weight: bold;';
			}
			if ($arr['R']['font-style'] == 'I' || $arr['R']['font-style'] == 'BI') {
				$html .= ' font-style: italic;';
			}
		}
		$html .= '">' . $rContent . '</td>';
		$html .= '</tr></table>';
		return $html;
	}

	/* -- BORDER-RADIUS -- */

	function _borderPadding($a, $b, &$px, &$py)
	{
		// $px and py are padding long axis (x) and short axis (y)
		$added = 0; // extra padding

		$x = $a - $px;
		$y = $b - $py;
		// Check if Falls within ellipse of border radius
		if (( (($x + $added) * ($x + $added)) / ($a * $a) + (($y + $added) * ($y + $added)) / ($b * $b) ) <= 1) {
			return false;
		}

		$t = atan2($y, $x);

		$newx = $b / sqrt((($b * $b) / ($a * $a)) + ( tan($t) * tan($t) ));
		$newy = $a / sqrt((($a * $a) / ($b * $b)) + ( (1 / tan($t)) * (1 / tan($t)) ));
		$px = max($px, $a - $newx + $added);
		$py = max($py, $b - $newy + $added);
	}

	/* -- END BORDER-RADIUS -- */
	/* -- HTML-CSS -- */
	/* -- CSS-PAGE -- */

	/* -- END CSS-FLOAT -- */

	// LIST MARKERS	// mPDF 6  Lists
	function _setListMarker($listitemtype, $listitemimage, $listitemposition)
	{
		// if position:inside (and NOT table) - output now as a textbuffer; (so if next is block, will move to new line)
		// elseif position:outside (and NOT table) - output in front of first textbuffer output by setting listitem (cf. _saveTextBuffer)
		$e = '';
		$this->listitem = '';
		$spacer = ' ';
		// IMAGE
		if ($listitemimage && $listitemimage != 'none') {
			$listitemimage = trim(preg_replace('/url\(["\']*(.*?)["\']*\)/', '\\1', $listitemimage));

			// ? Restrict maximum height/width of list marker??
			$maxWidth = 100;
			$maxHeight = 100;

			$objattr = [];
			$objattr['margin_top'] = 0;
			$objattr['margin_bottom'] = 0;
			$objattr['margin_left'] = 0;
			$objattr['margin_right'] = 0;
			$objattr['padding_top'] = 0;
			$objattr['padding_bottom'] = 0;
			$objattr['padding_left'] = 0;
			$objattr['padding_right'] = 0;
			$objattr['width'] = 0;
			$objattr['height'] = 0;
			$objattr['border_top']['w'] = 0;
			$objattr['border_bottom']['w'] = 0;
			$objattr['border_left']['w'] = 0;
			$objattr['border_right']['w'] = 0;
			$objattr['visibility'] = 'visible';
			$srcpath = $listitemimage;
			$orig_srcpath = $listitemimage;

			$objattr['vertical-align'] = 'BS'; // vertical alignment of marker (baseline)
			$w = 0;
			$h = 0;

			// Image file
			$info = $this->imageProcessor->getImage($srcpath, true, true, $orig_srcpath);
			if (!$info) {
				return;
			}

			if ($info['w'] == 0 && $info['h'] == 0) {
				$info['h'] = $this->sizeConverter->convert('1em', $this->blk[$this->blklvl]['inner_width'], $this->FontSize, false);
			}

			$objattr['file'] = $srcpath;

			// Default width and height calculation if needed
			if ($w == 0 and $h == 0) {
				/* -- IMAGES-WMF -- */
				if ($info['type'] == 'wmf') {
					// WMF units are twips (1/20pt)
					// divide by 20 to get points
					// divide by k to get user units
					$w = abs($info['w']) / (20 * Mpdf::SCALE);
					$h = abs($info['h']) / (20 * Mpdf::SCALE);
				} else { 				/* -- END IMAGES-WMF -- */
					if ($info['type'] == 'svg') {
						// SVG units are pixels
						$w = abs($info['w']) / Mpdf::SCALE;
						$h = abs($info['h']) / Mpdf::SCALE;
					} else {
						// Put image at default image dpi
						$w = ($info['w'] / Mpdf::SCALE) * (72 / $this->img_dpi);
						$h = ($info['h'] / Mpdf::SCALE) * (72 / $this->img_dpi);
					}
				}
			}
			// IF WIDTH OR HEIGHT SPECIFIED
			if ($w == 0) {
				$w = abs($h * $info['w'] / $info['h']);
			}
			if ($h == 0) {
				$h = abs($w * $info['h'] / $info['w']);
			}

			if ($w > $maxWidth) {
				$w = $maxWidth;
				$h = abs($w * $info['h'] / $info['w']);
			}

			if ($h > $maxHeight) {
				$h = $maxHeight;
				$w = abs($h * $info['w'] / $info['h']);
			}

			$objattr['type'] = 'image';
			$objattr['itype'] = $info['type'];

			$objattr['orig_h'] = $info['h'];
			$objattr['orig_w'] = $info['w'];

			/* -- IMAGES-WMF -- */
			if ($info['type'] == 'wmf') {
				$objattr['wmf_x'] = $info['x'];
				$objattr['wmf_y'] = $info['y'];
			} else { 			/* -- END IMAGES-WMF -- */
				if ($info['type'] == 'svg') {
					$objattr['wmf_x'] = $info['x'];
					$objattr['wmf_y'] = $info['y'];
				}
			}

			$objattr['height'] = $h;
			$objattr['width'] = $w;
			$objattr['image_height'] = $h;
			$objattr['image_width'] = $w;

			$objattr['dir'] = (isset($this->blk[$this->blklvl]['direction']) ? $this->blk[$this->blklvl]['direction'] : 'ltr');
			$objattr['listmarker'] = true;

			$objattr['listmarkerposition'] = $listitemposition;

			$e = "\xbb\xa4\xactype=image,objattr=" . serialize($objattr) . "\xbb\xa4\xac";
			$this->_saveTextBuffer($e);

			if ($listitemposition == 'inside') {
				$e = $spacer;
				$this->_saveTextBuffer($e);
			}
		} elseif ($listitemtype == 'disc' || $listitemtype == 'circle' || $listitemtype == 'square') { // SYMBOL (needs new font)
			$objattr = [];
			$objattr['type'] = 'listmarker';
			$objattr['listmarkerposition'] = $listitemposition;
			$objattr['width'] = 0;
			$size = $this->sizeConverter->convert($this->list_symbol_size, $this->FontSize);
			$objattr['size'] = $size;
			$objattr['offset'] = $this->sizeConverter->convert($this->list_marker_offset, $this->FontSize);

			if ($listitemposition == 'inside') {
				$objattr['width'] = $size + $objattr['offset'];
			}

			$objattr['height'] = $this->FontSize;
			$objattr['vertical-align'] = 'T';
			$objattr['text'] = '';
			$objattr['dir'] = (isset($this->blk[$this->blklvl]['direction']) ? $this->blk[$this->blklvl]['direction'] : 'ltr');
			$objattr['bullet'] = $listitemtype;
			$objattr['colorarray'] = $this->colorarray;
			$objattr['fontfamily'] = $this->FontFamily;
			$objattr['fontsize'] = $this->FontSize;
			$objattr['fontsizept'] = $this->FontSizePt;
			$objattr['fontstyle'] = $this->FontStyle;

			$e = "\xbb\xa4\xactype=listmarker,objattr=" . serialize($objattr) . "\xbb\xa4\xac";
			$this->listitem = $this->_saveTextBuffer($e, '', '', true); // true returns array

		} elseif (preg_match('/U\+([a-fA-F0-9]+)/i', $listitemtype, $m)) { // SYMBOL 2 (needs new font)

			if ($this->_charDefined($this->CurrentFont['cw'], hexdec($m[1]))) {
				$list_item_marker = UtfString::codeHex2utf($m[1]);
			} else {
				$list_item_marker = '-';
			}
			if (preg_match('/rgb\(.*?\)/', $listitemtype, $m)) {
				$list_item_color = $this->colorConverter->convert($m[0], $this->PDFAXwarnings);
			} else {
				$list_item_color = '';
			}

			// SAVE then SET COLR
			$save_colorarray = $this->colorarray;
			if ($list_item_color) {
				$this->colorarray = $list_item_color;
			}

			if ($listitemposition == 'inside') {
				$e = $list_item_marker . $spacer;
				$this->_saveTextBuffer($e);
			} else {
				$objattr = [];
				$objattr['type'] = 'listmarker';
				$objattr['width'] = 0;
				$objattr['height'] = $this->FontSize;
				$objattr['vertical-align'] = 'T';
				$objattr['text'] = $list_item_marker;
				$objattr['dir'] = (isset($this->blk[$this->blklvl]['direction']) ? $this->blk[$this->blklvl]['direction'] : 'ltr');
				$objattr['colorarray'] = $this->colorarray;
				$objattr['fontfamily'] = $this->FontFamily;
				$objattr['fontsize'] = $this->FontSize;
				$objattr['fontsizept'] = $this->FontSizePt;
				$objattr['fontstyle'] = $this->FontStyle;
				$e = "\xbb\xa4\xactype=listmarker,objattr=" . serialize($objattr) . "\xbb\xa4\xac";
				$this->listitem = $this->_saveTextBuffer($e, '', '', true); // true returns array
			}

			// RESET COLOR
			$this->colorarray = $save_colorarray;

		} else { // TEXT
			$counter = $this->listcounter[$this->listlvl];

			if ($listitemtype == 'none') {
				return;
			}

			$num = $this->_getStyledNumber($counter, $listitemtype, true);

			if ($listitemposition == 'inside') {
				$e = $num . $this->list_number_suffix . $spacer;
				$this->_saveTextBuffer($e);
			} else {
				if (isset($this->blk[$this->blklvl]['direction']) && $this->blk[$this->blklvl]['direction'] == 'rtl') {
					// REPLACE MIRRORED RTL $this->list_number_suffix  e.g. ) -> (  (NB could use Ucdn::$mirror_pairs)
					$m = strtr($this->list_number_suffix, ")]}", "([{") . $num;
				} else {
					$m = $num . $this->list_number_suffix;
				}

				$objattr = [];
				$objattr['type'] = 'listmarker';
				$objattr['width'] = 0;
				$objattr['height'] = $this->FontSize;
				$objattr['vertical-align'] = 'T';
				$objattr['text'] = $m;
				$objattr['dir'] = (isset($this->blk[$this->blklvl]['direction']) ? $this->blk[$this->blklvl]['direction'] : 'ltr');
				$objattr['colorarray'] = $this->colorarray;
				$objattr['fontfamily'] = $this->FontFamily;
				$objattr['fontsize'] = $this->FontSize;
				$objattr['fontsizept'] = $this->FontSizePt;
				$objattr['fontstyle'] = $this->FontStyle;
				$e = "\xbb\xa4\xactype=listmarker,objattr=" . serialize($objattr) . "\xbb\xa4\xac";

				$this->listitem = $this->_saveTextBuffer($e, '', '', true); // true returns array
			}
		}
	}

	// mPDF Lists
	function _getListMarkerWidth(&$currblk, &$a, &$i)
	{
		$blt_width = 0;

		$markeroffset = $this->sizeConverter->convert($this->list_marker_offset, $this->FontSize);

		// Get Maximum number in the list
		$maxnum = $this->listcounter[$this->listlvl];
		if ($currblk['list_style_type'] != 'disc' && $currblk['list_style_type'] != 'circle' && $currblk['list_style_type'] != 'square') {
			$lvl = 1;
			for ($j = $i + 2; $j < count($a); $j+=2) {
				$e = $a[$j];
				if (!$e) {
					continue;
				}
				if ($e[0] == '/') { // end tag
					$e = strtoupper(substr($e, 1));
					if ($e == 'OL' || $e == 'UL') {
						if ($lvl == 1) {
							break;
						}
						$lvl--;
					}
				} else { // opening tag
					if (strpos($e, ' ')) {
						$e = substr($e, 0, strpos($e, ' '));
					}
					$e = strtoupper($e);
					if ($e == 'LI') {
						if ($lvl == 1) {
							$maxnum++;
						}
					} elseif ($e == 'OL' || $e == 'UL') {
						$lvl++;
					}
				}
			}
		}

		$decToAlpha = new Conversion\DecToAlpha();
		$decToRoman = new Conversion\DecToRoman();
		$decToOther = new Conversion\DecToOther($this);

		switch ($currblk['list_style_type']) {
			case 'decimal':
			case '1':
				$blt_width = $this->GetStringWidth(str_repeat('5', strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'none':
				$blt_width = 0;
				break;
			case 'upper-alpha':
			case 'upper-latin':
			case 'A':
				$maxnumA = $decToAlpha->convert($maxnum, true);
				if ($maxnum < 13) {
					$blt_width = $this->GetStringWidth('D' . $this->list_number_suffix);
				} else {
					$blt_width = $this->GetStringWidth(str_repeat('W', strlen($maxnumA)) . $this->list_number_suffix);
				}
				break;
			case 'lower-alpha':
			case 'lower-latin':
			case 'a':
				$maxnuma = $decToAlpha->convert($maxnum, false);
				if ($maxnum < 13) {
					$blt_width = $this->GetStringWidth('b' . $this->list_number_suffix);
				} else {
					$blt_width = $this->GetStringWidth(str_repeat('m', strlen($maxnuma)) . $this->list_number_suffix);
				}
				break;
			case 'upper-roman':
			case 'I':
				if ($maxnum > 87) {
					$bbit = 87;
				} elseif ($maxnum > 86) {
					$bbit = 86;
				} elseif ($maxnum > 37) {
					$bbit = 38;
				} elseif ($maxnum > 36) {
					$bbit = 37;
				} elseif ($maxnum > 27) {
					$bbit = 28;
				} elseif ($maxnum > 26) {
					$bbit = 27;
				} elseif ($maxnum > 17) {
					$bbit = 18;
				} elseif ($maxnum > 16) {
					$bbit = 17;
				} elseif ($maxnum > 7) {
					$bbit = 8;
				} elseif ($maxnum > 6) {
					$bbit = 7;
				} elseif ($maxnum > 3) {
					$bbit = 4;
				} else {
					$bbit = $maxnum;
				}

				$maxlnum = $decToRoman->convert($bbit, true);
				$blt_width = $this->GetStringWidth($maxlnum . $this->list_number_suffix);

				break;
			case 'lower-roman':
			case 'i':
				if ($maxnum > 87) {
					$bbit = 87;
				} elseif ($maxnum > 86) {
					$bbit = 86;
				} elseif ($maxnum > 37) {
					$bbit = 38;
				} elseif ($maxnum > 36) {
					$bbit = 37;
				} elseif ($maxnum > 27) {
					$bbit = 28;
				} elseif ($maxnum > 26) {
					$bbit = 27;
				} elseif ($maxnum > 17) {
					$bbit = 18;
				} elseif ($maxnum > 16) {
					$bbit = 17;
				} elseif ($maxnum > 7) {
					$bbit = 8;
				} elseif ($maxnum > 6) {
					$bbit = 7;
				} elseif ($maxnum > 3) {
					$bbit = 4;
				} else {
					$bbit = $maxnum;
				}
				$maxlnum = $decToRoman->convert($bbit, false);
				$blt_width = $this->GetStringWidth($maxlnum . $this->list_number_suffix);
				break;

			case 'disc':
			case 'circle':
			case 'square':
				$size = $this->sizeConverter->convert($this->list_symbol_size, $this->FontSize);
				$offset = $this->sizeConverter->convert($this->list_marker_offset, $this->FontSize);
				$blt_width = $size + $offset;
				break;

			case 'arabic-indic':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0660), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'persian':
			case 'urdu':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x06F0), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'bengali':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x09E6), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'devanagari':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0966), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'gujarati':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0AE6), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'gurmukhi':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0A66), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'kannada':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0CE6), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'malayalam':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(6, 0x0D66), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'oriya':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0B66), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'telugu':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(3, 0x0C66), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'tamil':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(9, 0x0BE6), strlen($maxnum)) . $this->list_number_suffix);
				break;
			case 'thai':
				$blt_width = $this->GetStringWidth(str_repeat($decToOther->convert(5, 0x0E50), strlen($maxnum)) . $this->list_number_suffix);
				break;
			default:
				$blt_width = $this->GetStringWidth(str_repeat('5', strlen($maxnum)) . $this->list_number_suffix);
				break;
		}

		return ($blt_width + $markeroffset);
	}

	function _saveTextBuffer($t, $link = '', $intlink = '', $return = false)
	{
	// mPDF 6  Lists
		$arr = [];
		$arr[0] = $t;
		if (isset($link) && $link) {
			$arr[1] = $link;
		}
		$arr[2] = $this->currentfontstyle;
		if (isset($this->colorarray) && $this->colorarray) {
			$arr[3] = $this->colorarray;
		}
		$arr[4] = $this->currentfontfamily;
		$arr[5] = $this->currentLang; // mPDF 6
		if (isset($intlink) && $intlink) {
			$arr[7] = $intlink;
		}
		// mPDF 6
		// If Kerning set for OTL, and useOTL has positive value, but has not set for this particular script,
		// set for kerning via kern table
		// e.g. Latin script when useOTL set as 0x80
		if (isset($this->OTLtags['Plus']) && strpos($this->OTLtags['Plus'], 'kern') !== false && empty($this->OTLdata['GPOSinfo'])) {
			$this->textvar = ($this->textvar | TextVars::FC_KERNING);
		}
		$arr[8] = $this->textvar; // mPDF 5.7.1
		if (isset($this->textparam) && $this->textparam) {
			$arr[9] = $this->textparam;
		}
		if (isset($this->spanbgcolorarray) && $this->spanbgcolorarray) {
			$arr[10] = $this->spanbgcolorarray;
		}
		$arr[11] = $this->currentfontsize;
		if (isset($this->ReqFontStyle) && $this->ReqFontStyle) {
			$arr[12] = $this->ReqFontStyle;
		}
		if (isset($this->lSpacingCSS) && $this->lSpacingCSS) {
			$arr[14] = $this->lSpacingCSS;
		}
		if (isset($this->wSpacingCSS) && $this->wSpacingCSS) {
			$arr[15] = $this->wSpacingCSS;
		}
		if (isset($this->spanborddet) && $this->spanborddet) {
			$arr[16] = $this->spanborddet;
		}
		if (isset($this->textshadow) && $this->textshadow) {
			$arr[17] = $this->textshadow;
		}
		if (isset($this->OTLdata) && $this->OTLdata) {
			$arr[18] = $this->OTLdata;
			$this->OTLdata = [];
		} // mPDF 5.7.1
		else {
			$arr[18] = null;
		}
		// mPDF 6  Lists
		if ($return) {
			return ($arr);
		}
		if ($this->listitem) {
			$this->textbuffer[] = $this->listitem;
			$this->listitem = [];
		}
		$this->textbuffer[] = $arr;
	}

	function _saveCellTextBuffer($t, $link = '', $intlink = '')
	{
		$arr = [];
		$arr[0] = $t;
		if (isset($link) && $link) {
			$arr[1] = $link;
		}
		$arr[2] = $this->currentfontstyle;
		if (isset($this->colorarray) && $this->colorarray) {
			$arr[3] = $this->colorarray;
		}
		$arr[4] = $this->currentfontfamily;
		if (isset($intlink) && $intlink) {
			$arr[7] = $intlink;
		}
		// mPDF 6
		// If Kerning set for OTL, and useOTL has positive value, but has not set for this particular script,
		// set for kerning via kern table
		// e.g. Latin script when useOTL set as 0x80
		if (isset($this->OTLtags['Plus']) && strpos($this->OTLtags['Plus'], 'kern') !== false && empty($this->OTLdata['GPOSinfo'])) {
			$this->textvar = ($this->textvar | TextVars::FC_KERNING);
		}
		$arr[8] = $this->textvar; // mPDF 5.7.1
		if (isset($this->textparam) && $this->textparam) {
			$arr[9] = $this->textparam;
		}
		if (isset($this->spanbgcolorarray) && $this->spanbgcolorarray) {
			$arr[10] = $this->spanbgcolorarray;
		}
		$arr[11] = $this->currentfontsize;
		if (isset($this->ReqFontStyle) && $this->ReqFontStyle) {
			$arr[12] = $this->ReqFontStyle;
		}
		if (isset($this->lSpacingCSS) && $this->lSpacingCSS) {
			$arr[14] = $this->lSpacingCSS;
		}
		if (isset($this->wSpacingCSS) && $this->wSpacingCSS) {
			$arr[15] = $this->wSpacingCSS;
		}
		if (isset($this->spanborddet) && $this->spanborddet) {
			$arr[16] = $this->spanborddet;
		}
		if (isset($this->textshadow) && $this->textshadow) {
			$arr[17] = $this->textshadow;
		}
		if (isset($this->OTLdata) && $this->OTLdata) {
			$arr[18] = $this->OTLdata;
			$this->OTLdata = [];
		} // mPDF 5.7.1
		else {
			$arr[18] = null;
		}
		$this->cell[$this->row][$this->col]['textbuffer'][] = $arr;
	}

	function _setDashBorder($style, $div, $cp, $side)
	{
		if ($style == 'dashed' && (($side == 'L' || $side == 'R') || ($side == 'T' && $div != 'pagetop' && !$cp) || ($side == 'B' && $div != 'pagebottom') )) {
			$dashsize = 2; // final dash will be this + 1*linewidth
			$dashsizek = 1.5; // ratio of Dash/Blank
			$this->SetDash($dashsize, ($dashsize / $dashsizek) + ($this->LineWidth * 2));
		} elseif ($style == 'dotted' || ($side == 'T' && ($div == 'pagetop' || $cp)) || ($side == 'B' && $div == 'pagebottom')) {
			// Round join and cap
			$this->SetLineJoin(1);
			$this->SetLineCap(1);
			$this->SetDash(0.001, ($this->LineWidth * 3));
		}
	}

	function _setBorderLine($b, $k = 1)
	{
		$this->SetLineWidth($b['w'] / $k);
		$this->SetDColor($b['c']);
		if ($b['c'][0] == 5) { // RGBa
			$this->SetAlpha(ord($b['c'][4]) / 100, 'Normal', false, 'S'); // mPDF 5.7.2
		} elseif ($b['c'][0] == 6) { // CMYKa
			$this->SetAlpha(ord($b['c'][5]) / 100, 'Normal', false, 'S'); // mPDF 5.7.2
		}
	}

	/* -- BORDER-RADIUS -- */

	function _EllipseArc($x0, $y0, $rx, $ry, $seg = 1, $part = false, $start = false)
	{
	// Anticlockwise segment 1-4 TR-TL-BL-BR (part=1 or 2)
		$s = '';
		if ($rx < 0) {
			$rx = 0;
		}
		if ($ry < 0) {
			$ry = 0;
		}
		$rx *= Mpdf::SCALE;
		$ry *= Mpdf::SCALE;
		$astart = 0;
		if ($seg == 1) { // Top Right
			$afinish = 90;
			$nSeg = 4;
		} elseif ($seg == 2) { // Top Left
			$afinish = 180;
			$nSeg = 8;
		} elseif ($seg == 3) { // Bottom Left
			$afinish = 270;
			$nSeg = 12;
		} else {   // Bottom Right
			$afinish = 360;
			$nSeg = 16;
		}
		$astart = deg2rad((float) $astart);
		$afinish = deg2rad((float) $afinish);
		$totalAngle = $afinish - $astart;
		$dt = $totalAngle / $nSeg; // segment angle
		$dtm = $dt / 3;
		$x0 *= Mpdf::SCALE;
		$y0 = ($this->h - $y0) * Mpdf::SCALE;
		$t1 = $astart;
		$a0 = $x0 + ($rx * cos($t1));
		$b0 = $y0 + ($ry * sin($t1));
		$c0 = -$rx * sin($t1);
		$d0 = $ry * cos($t1);
		$op = false;
		for ($i = 1; $i <= $nSeg; $i++) {
			// Draw this bit of the total curve
			$t1 = ($i * $dt) + $astart;
			$a1 = $x0 + ($rx * cos($t1));
			$b1 = $y0 + ($ry * sin($t1));
			$c1 = -$rx * sin($t1);
			$d1 = $ry * cos($t1);
			if ($i > ($nSeg - 4) && (!$part || ($part == 1 && $i <= $nSeg - 2) || ($part == 2 && $i > $nSeg - 2))) {
				if ($start && !$op) {
					$s .= sprintf('%.3F %.3F m ', $a0, $b0);
				}
				$s .= sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c ', ($a0 + ($c0 * $dtm)), ($b0 + ($d0 * $dtm)), ($a1 - ($c1 * $dtm)), ($b1 - ($d1 * $dtm)), $a1, $b1);
				$op = true;
			}
			$a0 = $a1;
			$b0 = $b1;
			$c0 = $c1;
			$d0 = $d1;
		}
		return $s;
	}

	/* -- END BORDER-RADIUS -- */

	/* -- END HTML-CSS -- */

	function _packCellBorder($cell)
	{
		if (!is_array($cell) || !isset($cell)) {
			return '';
		}

		if (!$this->packTableData) {
			return $cell;
		}
		// = 186 bytes
		$bindata = pack("nnda6A10nnda6A10nnda6A10nnda6A10nd9", $cell['border'], $cell['border_details']['R']['s'], $cell['border_details']['R']['w'], $cell['border_details']['R']['c'], $cell['border_details']['R']['style'], $cell['border_details']['R']['dom'], $cell['border_details']['L']['s'], $cell['border_details']['L']['w'], $cell['border_details']['L']['c'], $cell['border_details']['L']['style'], $cell['border_details']['L']['dom'], $cell['border_details']['T']['s'], $cell['border_details']['T']['w'], $cell['border_details']['T']['c'], $cell['border_details']['T']['style'], $cell['border_details']['T']['dom'], $cell['border_details']['B']['s'], $cell['border_details']['B']['w'], $cell['border_details']['B']['c'], $cell['border_details']['B']['style'], $cell['border_details']['B']['dom'], $cell['border_details']['mbw']['BL'], $cell['border_details']['mbw']['BR'], $cell['border_details']['mbw']['RT'], $cell['border_details']['mbw']['RB'], $cell['border_details']['mbw']['TL'], $cell['border_details']['mbw']['TR'], $cell['border_details']['mbw']['LT'], $cell['border_details']['mbw']['LB'], (isset($cell['border_details']['cellposdom']) ? $cell['border_details']['cellposdom'] : 0));
		return $bindata;
	}

	function _getBorderWidths($bindata)
	{
		if (!$bindata) {
			return [0, 0, 0, 0];
		}
		if (!$this->packTableData) {
			return [$bindata['border_details']['T']['w'], $bindata['border_details']['R']['w'], $bindata['border_details']['B']['w'], $bindata['border_details']['L']['w']];
		}

		$bd = unpack("nbord/nrs/drw/a6rca/A10rst/nrd/nls/dlw/a6lca/A10lst/nld/nts/dtw/a6tca/A10tst/ntd/nbs/dbw/a6bca/A10bst/nbd/dmbl/dmbr/dmrt/dmrb/dmtl/dmtr/dmlt/dmlb/dcpd", $bindata);
		$cell['border_details']['R']['w'] = $bd['rw'];
		$cell['border_details']['L']['w'] = $bd['lw'];
		$cell['border_details']['T']['w'] = $bd['tw'];
		$cell['border_details']['B']['w'] = $bd['bw'];
		return [$bd['tw'], $bd['rw'], $bd['bw'], $bd['lw']];
	}

	function _unpackCellBorder($bindata)
	{
		if (!$bindata) {
			return [];
		}
		if (!$this->packTableData) {
			return $bindata;
		}

		$bd = unpack("nbord/nrs/drw/a6rca/A10rst/nrd/nls/dlw/a6lca/A10lst/nld/nts/dtw/a6tca/A10tst/ntd/nbs/dbw/a6bca/A10bst/nbd/dmbl/dmbr/dmrt/dmrb/dmtl/dmtr/dmlt/dmlb/dcpd", $bindata);

		$cell['border'] = $bd['bord'];
		$cell['border_details']['R']['s'] = $bd['rs'];
		$cell['border_details']['R']['w'] = $bd['rw'];
		$cell['border_details']['R']['c'] = str_pad($bd['rca'], 6, "\x00");
		$cell['border_details']['R']['style'] = trim($bd['rst']);
		$cell['border_details']['R']['dom'] = $bd['rd'];

		$cell['border_details']['L']['s'] = $bd['ls'];
		$cell['border_details']['L']['w'] = $bd['lw'];
		$cell['border_details']['L']['c'] = str_pad($bd['lca'], 6, "\x00");
		$cell['border_details']['L']['style'] = trim($bd['lst']);
		$cell['border_details']['L']['dom'] = $bd['ld'];

		$cell['border_details']['T']['s'] = $bd['ts'];
		$cell['border_details']['T']['w'] = $bd['tw'];
		$cell['border_details']['T']['c'] = str_pad($bd['tca'], 6, "\x00");
		$cell['border_details']['T']['style'] = trim($bd['tst']);
		$cell['border_details']['T']['dom'] = $bd['td'];

		$cell['border_details']['B']['s'] = $bd['bs'];
		$cell['border_details']['B']['w'] = $bd['bw'];
		$cell['border_details']['B']['c'] = str_pad($bd['bca'], 6, "\x00");
		$cell['border_details']['B']['style'] = trim($bd['bst']);
		$cell['border_details']['B']['dom'] = $bd['bd'];

		$cell['border_details']['mbw']['BL'] = $bd['mbl'];
		$cell['border_details']['mbw']['BR'] = $bd['mbr'];
		$cell['border_details']['mbw']['RT'] = $bd['mrt'];
		$cell['border_details']['mbw']['RB'] = $bd['mrb'];
		$cell['border_details']['mbw']['TL'] = $bd['mtl'];
		$cell['border_details']['mbw']['TR'] = $bd['mtr'];
		$cell['border_details']['mbw']['LT'] = $bd['mlt'];
		$cell['border_details']['mbw']['LB'] = $bd['mlb'];
		$cell['border_details']['cellposdom'] = $bd['cpd'];


		return($cell);
	}

	////////////////////////TABLE CODE (from PDFTable)/////////////////////////////////////
	////////////////////////TABLE CODE (from PDFTable)/////////////////////////////////////
	////////////////////////TABLE CODE (from PDFTable)/////////////////////////////////////
	// table		Array of (w, h, bc, nr, wc, hr, cells)
	// w			Width of table
	// h			Height of table
	// nc			Number column
	// nr			Number row
	// hr			List of height of each row
	// wc			List of width of each column
	// cells		List of cells of each rows, cells[i][j] is a cell in the table
	function _tableColumnWidth(&$table, $firstpass = false)
	{
		$cs = &$table['cells'];

		$nc = $table['nc'];
		$nr = $table['nr'];
		$listspan = [];

		if ($table['borders_separate']) {
			$tblbw = $table['border_details']['L']['w'] + $table['border_details']['R']['w'] + $table['margin']['L'] + $table['margin']['R'] + $table['padding']['L'] + $table['padding']['R'] + $table['border_spacing_H'];
		} else {
			$tblbw = $table['max_cell_border_width']['L'] / 2 + $table['max_cell_border_width']['R'] / 2 + $table['margin']['L'] + $table['margin']['R'];
		}

		// ADDED table['l'][colno]
		// = total length of text approx (using $c['s']) in that column - used to approximately distribute col widths in _tableWidth
		//
		for ($j = 0; $j < $nc; $j++) { // columns
			$wc = &$table['wc'][$j];
			for ($i = 0; $i < $nr; $i++) { // rows
				if (isset($cs[$i][$j]) && $cs[$i][$j]) {
					$c = &$cs[$i][$j];

					if ($this->simpleTables) {
						if ($table['borders_separate']) { // NB twice border width
							$extrcw = $table['simple']['border_details']['L']['w'] + $table['simple']['border_details']['R']['w'] + $c['padding']['L'] + $c['padding']['R'] + $table['border_spacing_H'];
						} else {
							$extrcw = $table['simple']['border_details']['L']['w'] / 2 + $table['simple']['border_details']['R']['w'] / 2 + $c['padding']['L'] + $c['padding']['R'];
						}
					} else {
						if ($this->packTableData) {
							list($bt, $br, $bb, $bl) = $this->_getBorderWidths($c['borderbin']);
						} else {
							$br = $c['border_details']['R']['w'];
							$bl = $c['border_details']['L']['w'];
						}
						if ($table['borders_separate']) { // NB twice border width
							$extrcw = $bl + $br + $c['padding']['L'] + $c['padding']['R'] + $table['border_spacing_H'];
						} else {
							$extrcw = $bl / 2 + $br / 2 + $c['padding']['L'] + $c['padding']['R'];
						}
					}

					// $mw = $this->GetStringWidth('W') + $extrcw ;
					$mw = $extrcw; // mPDF 6
					if (substr($c['a'], 0, 1) == 'D') {
						$mw = $table['decimal_align'][$j]['maxs0'] + $table['decimal_align'][$j]['maxs1'] + $extrcw;
					}

					$c['absmiw'] = $mw;

					if (isset($c['R']) && $c['R']) {
						$c['maw'] = $c['miw'] = $this->FontSize + $extrcw;
						if (isset($c['w'])) { // If cell width is specified
							if ($c['miw'] < $c['w']) {
								$c['miw'] = $c['w'];
							}
						}
						if (!isset($c['colspan'])) {
							if ($wc['miw'] < $c['miw']) {
								$wc['miw'] = $c['miw'];
							}
							if ($wc['maw'] < $c['maw']) {
								$wc['maw'] = $c['maw'];
							}

							if ($firstpass) {
								if (isset($table['l'][$j])) {
									$table['l'][$j] += $c['miw'];
								} else {
									$table['l'][$j] = $c['miw'];
								}
							}
						}
						if ($c['miw'] > $wc['miw']) {
							$wc['miw'] = $c['miw'];
						}
						if ($wc['miw'] > $wc['maw']) {
							$wc['maw'] = $wc['miw'];
						}
						continue;
					}

					if ($firstpass) {
						if (isset($c['s'])) {
							$c['s'] += $extrcw;
						}
						if (isset($c['maxs'])) {
							$c['maxs'] += $extrcw;
						}
						if (isset($c['nestedmiw'])) {
							$c['nestedmiw'] += $extrcw;
						}
						if (isset($c['nestedmaw'])) {
							$c['nestedmaw'] += $extrcw;
						}
					}


					// If minimum width has already been set by a nested table or inline object (image/form), use it
					if (isset($c['nestedmiw']) && (!isset($this->table[1][1]['overflow']) || $this->table[1][1]['overflow'] != 'visible')) {
						$miw = $c['nestedmiw'];
					} else {
						$miw = $mw;
					}

					if (isset($c['maxs']) && $c['maxs'] != '') {
						$c['s'] = $c['maxs'];
					}

					// If maximum width has already been set by a nested table, use it
					if (isset($c['nestedmaw'])) {
						$c['maw'] = $c['nestedmaw'];
					} else {
						$c['maw'] = $c['s'];
					}

					if (isset($table['overflow']) && $table['overflow'] == 'visible' && $table['level'] == 1) {
						if (($c['maw'] + $tblbw) > $this->blk[$this->blklvl]['inner_width']) {
							$c['maw'] = $this->blk[$this->blklvl]['inner_width'] - $tblbw;
						}
					}

					if (isset($c['nowrap']) && $c['nowrap']) {
						$miw = $c['maw'];
					}

					if (isset($c['wpercent']) && $firstpass) {
						if (isset($c['colspan'])) { // Not perfect - but % set on colspan is shared equally on cols.
							for ($k = 0; $k < $c['colspan']; $k++) {
								$table['wc'][($j + $k)]['wpercent'] = $c['wpercent'] / $c['colspan'];
							}
						} else {
							if (isset($table['w']) && $table['w']) {
								$c['w'] = $c['wpercent'] / 100 * ($table['w'] - $tblbw );
							}
							$wc['wpercent'] = $c['wpercent'];
						}
					}

					if (isset($table['overflow']) && $table['overflow'] == 'visible' && $table['level'] == 1) {
						if (isset($c['w']) && ($c['w'] + $tblbw) > $this->blk[$this->blklvl]['inner_width']) {
							$c['w'] = $this->blk[$this->blklvl]['inner_width'] - $tblbw;
						}
					}


					if (isset($c['w'])) { // If cell width is specified
						if ($miw < $c['w']) {
							$c['miw'] = $c['w'];
						} // Cell min width = that specified
						if ($miw > $c['w']) {
							$c['miw'] = $c['w'] = $miw;
						} // If width specified is less than minimum allowed (W) increase it
						// mPDF 5.7.4  Do not set column width in colspan
						// cf. http://www.mpdf1.com/forum/discussion/2221/colspan-bug
						if (!isset($c['colspan'])) {
							if (!isset($wc['w'])) {
								$wc['w'] = 1;
							}  // If the Col width is not specified = set it to 1
						}
						// mPDF 5.7.3  cf. http://www.mpdf1.com/forum/discussion/1648/nested-table-bug-
						$c['maw'] = $c['w'];
					} else {
						$c['miw'] = $miw;
					} // If cell width not specified -> set Cell min width it to minimum allowed (W)

					if (isset($c['miw']) && $c['maw'] < $c['miw']) {
						$c['maw'] = $c['miw'];
					} // If Cell max width < Minwidth - increase it to =
					if (!isset($c['colspan'])) {
						if (isset($c['miw']) && $wc['miw'] < $c['miw']) {
							$wc['miw'] = $c['miw'];
						} // Update Col Minimum and maximum widths
						if ($wc['maw'] < $c['maw']) {
							$wc['maw'] = $c['maw'];
						}
						if ((isset($wc['absmiw']) && $wc['absmiw'] < $c['absmiw']) || !isset($wc['absmiw'])) {
							$wc['absmiw'] = $c['absmiw'];
						} // Update Col Minimum and maximum widths

						if (isset($table['l'][$j])) {
							$table['l'][$j] += $c['s'];
						} else {
							$table['l'][$j] = $c['s'];
						}
					} else {
						$listspan[] = [$i, $j];
					}

					// Check if minimum width of the whole column is big enough for largest word to fit
					// mPDF 6
					if (isset($c['textbuffer'])) {
						if (isset($table['overflow']) && $table['overflow'] == 'wrap') {
							$letter = true;
						} // check for maximum width of letters
						else {
							$letter = false;
						}
						$minwidth = $this->TableCheckMinWidth($wc['miw'] - $extrcw, 0, $c['textbuffer'], $letter);
					} else {
						$minwidth = 0;
					}
					if ($minwidth < 0) {
						// increase minimum width
						if (!isset($c['colspan'])) {
							$wc['miw'] = max($wc['miw'], ((-$minwidth) + $extrcw));
						} else {
							$c['miw'] = max($c['miw'], ((-$minwidth) + $extrcw));
						}
					}
					if (!isset($c['colspan'])) {
						if ($wc['miw'] > $wc['maw']) {
							$wc['maw'] = $wc['miw'];
						} // update maximum width, if needed
					}
				}
				unset($c);
			}//rows
		}//columns
		// COLUMN SPANS
		$wc = &$table['wc'];
		foreach ($listspan as $span) {
			list($i, $j) = $span;
			$c = &$cs[$i][$j];
			$lc = $j + $c['colspan'];
			if ($lc > $nc) {
				$lc = $nc;
			}
			$wis = $wisa = 0;
			$was = $wasa = 0;
			$list = [];
			for ($k = $j; $k < $lc; $k++) {
				if (isset($table['l'][$k])) {
					if ($c['R']) {
						$table['l'][$k] += $c['miw'] / $c['colspan'];
					} else {
						$table['l'][$k] += $c['s'] / $c['colspan'];
					}
				} else {
					if ($c['R']) {
						$table['l'][$k] = $c['miw'] / $c['colspan'];
					} else {
						$table['l'][$k] = $c['s'] / $c['colspan'];
					}
				}
				$wis += $wc[$k]['miw'];   // $wis is the sum of the column miw in the colspan
				$was += $wc[$k]['maw'];   // $was is the sum of the column maw in the colspan
				if (!isset($c['w'])) {
					$list[] = $k;
					$wisa += $wc[$k]['miw']; // $wisa is the sum of the column miw in cells with no width specified in the colspan
					$wasa += $wc[$k]['maw']; // $wasa is the sum of the column maw in cells with no width specified in the colspan
				}
			}
			if ($c['miw'] > $wis) {
				if (!$wis) {
					for ($k = $j; $k < $lc; $k++) {
						$wc[$k]['miw'] = $c['miw'] / $c['colspan'];
					}
				} elseif (!count($list)) {
					$wi = $c['miw'] - $wis;
					for ($k = $j; $k < $lc; $k++) {
						$wc[$k]['miw'] += ($wc[$k]['miw'] / $wis) * $wi;
					}
				} else {
					$wi = $c['miw'] - $wis;
					// mPDF 5.7.2   Extra min width distributed proportionately to all cells in colspan without a specified width
					// cf. http://www.mpdf1.com/forum/discussion/1607#Item_4
					foreach ($list as $k) {
						if (!isset($wc[$k]['w']) || !$wc[$k]['w']) {
							$wc[$k]['miw'] += ($wc[$k]['miw'] / $wisa) * $wi;
						}
					} // mPDF 5.7.2
				}
			}
			if ($c['maw'] > $was) {
				if (!$wis) {
					for ($k = $j; $k < $lc; $k++) {
						$wc[$k]['maw'] = $c['maw'] / $c['colspan'];
					}
				} elseif (!count($list)) {
					$wi = $c['maw'] - $was;
					for ($k = $j; $k < $lc; $k++) {
						$wc[$k]['maw'] += ($wc[$k]['maw'] / $was) * $wi;
					}
				} else {
					$wi = $c['maw'] - $was;
					// mPDF 5.7.4  Extra max width distributed evenly to all cells in colspan without a specified width
					// cf. http://www.mpdf1.com/forum/discussion/2221/colspan-bug
					foreach ($list as $k) {
						$wc[$k]['maw'] += $wi / count($list);
					}
				}
			}
			unset($c);
		}

		$checkminwidth = 0;
		$checkmaxwidth = 0;
		$totallength = 0;

		for ($i = 0; $i < $nc; $i++) {
			$checkminwidth += $table['wc'][$i]['miw'];
			$checkmaxwidth += $table['wc'][$i]['maw'];
			$totallength += isset($table['l']) ? $table['l'][$i] : 0;
		}

		if (!isset($table['w']) && $firstpass) {
			$sumpc = 0;
			$notset = 0;
			for ($i = 0; $i < $nc; $i++) {
				if (isset($table['wc'][$i]['wpercent']) && $table['wc'][$i]['wpercent']) {
					$sumpc += $table['wc'][$i]['wpercent'];
				} else {
					$notset++;
				}
			}

			// If sum of widths as %  >= 100% and not all columns are set
			// Set a nominal width of 1% for unset columns
			if ($sumpc >= 100 && $notset) {
				for ($i = 0; $i < $nc; $i++) {
					if ((!isset($table['wc'][$i]['wpercent']) || !$table['wc'][$i]['wpercent']) &&
						(!isset($table['wc'][$i]['w']) || !$table['wc'][$i]['w'])) {
						$table['wc'][$i]['wpercent'] = 1;
					}
				}
			}


			if ($sumpc) { // if any percents are set
				$sumnonpc = (100 - $sumpc);
				$sumpc = max($sumpc, 100);
				$miwleft = 0;
				$miwleftcount = 0;
				$miwsurplusnonpc = 0;
				$maxcalcmiw = 0;
				$mawleft = 0;
				$mawleftcount = 0;
				$mawsurplusnonpc = 0;
				$maxcalcmaw = 0;
				$mawnon = 0;
				$miwnon = 0;
				for ($i = 0; $i < $nc; $i++) {
					if (isset($table['wc'][$i]['wpercent'])) {
						$maxcalcmiw = max($maxcalcmiw, ($table['wc'][$i]['miw'] * $sumpc / $table['wc'][$i]['wpercent']));
						$maxcalcmaw = max($maxcalcmaw, ($table['wc'][$i]['maw'] * $sumpc / $table['wc'][$i]['wpercent']));
					} else {
						$miwleft += $table['wc'][$i]['miw'];
						$mawleft += $table['wc'][$i]['maw'];
						if (!isset($table['wc'][$i]['w'])) {
							$miwleftcount++;
							$mawleftcount++;
						}
					}
				}
				if ($miwleft && $sumnonpc > 0) {
					$miwnon = $miwleft * 100 / $sumnonpc;
				}
				if ($mawleft && $sumnonpc > 0) {
					$mawnon = $mawleft * 100 / $sumnonpc;
				}
				if (($miwnon > $checkminwidth || $maxcalcmiw > $checkminwidth) && $this->keep_table_proportions) {
					if ($miwnon > $maxcalcmiw) {
						$miwsurplusnonpc = round((($miwnon * $sumnonpc / 100) - $miwleft), 3);
						$checkminwidth = $miwnon;
					} else {
						$checkminwidth = $maxcalcmiw;
					}
					for ($i = 0; $i < $nc; $i++) {
						if (isset($table['wc'][$i]['wpercent'])) {
							$newmiw = $checkminwidth * $table['wc'][$i]['wpercent'] / 100;
							if ($table['wc'][$i]['miw'] < $newmiw) {
								$table['wc'][$i]['miw'] = $newmiw;
							}
							$table['wc'][$i]['w'] = 1;
						} elseif ($miwsurplusnonpc && !$table['wc'][$i]['w']) {
							$table['wc'][$i]['miw'] += $miwsurplusnonpc / $miwleftcount;
						}
					}
				}
				if (($mawnon > $checkmaxwidth || $maxcalcmaw > $checkmaxwidth)) {
					if ($mawnon > $maxcalcmaw) {
						$mawsurplusnonpc = round((($mawnon * $sumnonpc / 100) - $mawleft), 3);
						$checkmaxwidth = $mawnon;
					} else {
						$checkmaxwidth = $maxcalcmaw;
					}
					for ($i = 0; $i < $nc; $i++) {
						if (isset($table['wc'][$i]['wpercent'])) {
							$newmaw = $checkmaxwidth * $table['wc'][$i]['wpercent'] / 100;
							if ($table['wc'][$i]['maw'] < $newmaw) {
								$table['wc'][$i]['maw'] = $newmaw;
							}
							$table['wc'][$i]['w'] = 1;
						} elseif ($mawsurplusnonpc && !$table['wc'][$i]['w']) {
							$table['wc'][$i]['maw'] += $mawsurplusnonpc / $mawleftcount;
						}
						if ($table['wc'][$i]['maw'] < $table['wc'][$i]['miw']) {
							$table['wc'][$i]['maw'] = $table['wc'][$i]['miw'];
						}
					}
				}
				if ($checkminwidth > $checkmaxwidth) {
					$checkmaxwidth = $checkminwidth;
				}
			}
		}

		if (isset($table['wpercent']) && $table['wpercent']) {
			$checkminwidth *= (100 / $table['wpercent']);
			$checkmaxwidth *= (100 / $table['wpercent']);
		}


		$checkminwidth += $tblbw;
		$checkmaxwidth += $tblbw;

		// Table['miw'] set by percent in first pass may be larger than sum of column miw
		if ((isset($table['miw']) && $checkminwidth > $table['miw']) || !isset($table['miw'])) {
			$table['miw'] = $checkminwidth;
		}
		if ((isset($table['maw']) && $checkmaxwidth > $table['maw']) || !isset($table['maw'])) {
			$table['maw'] = $checkmaxwidth;
		}
		$table['tl'] = $totallength;

		// mPDF 6
		if ($this->table_rotate) {
			$mxw = $this->tbrot_maxw;
		} else {
			$mxw = $this->blk[$this->blklvl]['inner_width'];
		}

		if (!isset($table['overflow'])) {
			$table['overflow'] = null;
		}

		if ($table['overflow'] == 'visible') {
			return [0, 0];
		} elseif ($table['overflow'] == 'hidden' && !$this->table_rotate && !$this->ColActive && $checkminwidth > $mxw) {
			$table['w'] = $table['miw'];
			return [0, 0];
		}
		// elseif ($table['overflow']=='wrap') { return array(0,0); }	// mPDF 6

		if (isset($table['w']) && $table['w']) {

			if ($table['w'] >= $checkminwidth && $table['w'] <= $mxw) {
				$table['maw'] = $mxw = $table['w'];
			} elseif ($table['w'] >= $checkminwidth && $table['w'] > $mxw && $this->keep_table_proportions) {
				$checkminwidth = $table['w'];
			} elseif ($table['w'] < $checkminwidth && $checkminwidth < $mxw && $this->keep_table_proportions) {
				$table['maw'] = $table['w'] = $checkminwidth;
			} else {
				unset($table['w']);
			}
		}

		$ratio = $checkminwidth / $mxw;

		if ($checkminwidth > $mxw) {
			return [($ratio + 0.001), $checkminwidth]; // 0.001 to allow for rounded numbers when resizing
		}

		unset($cs);

		return [0, 0];
	}

	function _tableWidth(&$table)
	{
		$widthcols = &$table['wc'];
		$numcols = $table['nc'];
		$tablewidth = 0;

		if ($table['borders_separate']) {
			$tblbw = $table['border_details']['L']['w'] + $table['border_details']['R']['w'] + $table['margin']['L'] + $table['margin']['R'] + $table['padding']['L'] + $table['padding']['R'] + $table['border_spacing_H'];
		} else {
			$tblbw = $table['max_cell_border_width']['L'] / 2 + $table['max_cell_border_width']['R'] / 2 + $table['margin']['L'] + $table['margin']['R'];
		}

		if ($table['level'] > 1 && isset($table['w'])) {

			if (isset($table['wpercent']) && $table['wpercent']) {
				$table['w'] = $temppgwidth = (($table['w'] - $tblbw) * $table['wpercent'] / 100) + $tblbw;
			} else {
				$temppgwidth = $table['w'];
			}

		} elseif ($this->table_rotate) {

			$temppgwidth = $this->tbrot_maxw;

			// If it is less than 1/20th of the remaining page height to finish the DIV (i.e. DIV padding + table bottom margin) then allow for this
			$enddiv = $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w'];

			if ($enddiv / $temppgwidth < 0.05) {
				$temppgwidth -= $enddiv;
			}

		} else {

			if (isset($table['w']) && $table['w'] < $this->blk[$this->blklvl]['inner_width']) {
				$notfullwidth = 1;
				$temppgwidth = $table['w'];
			} elseif ($table['overflow'] == 'visible' && $table['level'] == 1) {
				$temppgwidth = null;
			} elseif ($table['overflow'] == 'hidden' && !$this->ColActive && isset($table['w']) && $table['w'] > $this->blk[$this->blklvl]['inner_width'] && $table['w'] == $table) {
				// $temppgwidth = $this->blk[$this->blklvl]['inner_width'];
				$temppgwidth = $table['w'];
			} else {
				$temppgwidth = $this->blk[$this->blklvl]['inner_width'];
			}

		}

		$totaltextlength = 0; // Added - to sum $table['l'][colno]
		$totalatextlength = 0; // Added - to sum $table['l'][colno] for those columns where width not set
		$percentages_set = 0;

		for ($i = 0; $i < $numcols; $i++) {
			if (isset($widthcols[$i]['wpercent'])) {
				$tablewidth += $widthcols[$i]['maw'];
				$percentages_set = 1;
			} elseif (isset($widthcols[$i]['w'])) {
				$tablewidth += $widthcols[$i]['miw'];
			} else {
				$tablewidth += $widthcols[$i]['maw'];
			}
			$totaltextlength += isset($table['l']) ? $table['l'][$i] : 0;
		}

		if (!$totaltextlength) {
			$totaltextlength = 1;
		}

		$tablewidth += $tblbw; // Outer half of table borders

		if ($tablewidth > $temppgwidth) {
			$table['w'] = $temppgwidth;
		} elseif ($tablewidth < $temppgwidth && !isset($table['w']) && $percentages_set) { // if any widths set as percentages and max width fits < page width
			$table['w'] = $table['maw'];
		}

		// if table width is set and is > allowed width
		if (isset($table['w']) && $table['w'] > $temppgwidth) {
			$table['w'] = $temppgwidth;
		}

		// IF the table width is now set - Need to distribute columns widths
		// mPDF 5.7.3
		// If the table width is already set to the maximum width (e.g. nested table), then use maximum column widths exactly
		if (isset($table['w']) && ($table['w'] == $tablewidth) && !$percentages_set) {

			// This sets the columns all to maximum width
			for ($i = 0; $i < $numcols; $i++) {
				$widthcols[$i] = $widthcols[$i]['maw'];
			}

		} elseif (isset($table['w'])) { // elseif the table width is set distribute width using algorithm

			$wis = $wisa = 0;
			$list = [];
			$notsetlist = [];

			for ($i = 0; $i < $numcols; $i++) {
				$wis += $widthcols[$i]['miw'];
				if (!isset($widthcols[$i]['w']) || ($widthcols[$i]['w'] && $table['w'] > $temppgwidth && !$this->keep_table_proportions && !$notfullwidth )) {
					$list[] = $i;
					$wisa += $widthcols[$i]['miw'];
					$totalatextlength += $table['l'][$i];
				}
			}

			if (!$totalatextlength) {
				$totalatextlength = 1;
			}

			// Allocate spare (more than col's minimum width) across the cols according to their approx total text length
			// Do it by setting minimum width here
			if ($table['w'] > $wis + $tblbw) {

				// First set any cell widths set as percentages
				if ($table['w'] < $temppgwidth || $this->keep_table_proportions) {
					for ($k = 0; $k < $numcols; $k++) {
						if (isset($widthcols[$k]['wpercent'])) {
							$curr = $widthcols[$k]['miw'];
							$widthcols[$k]['miw'] = ($table['w'] - $tblbw) * $widthcols[$k]['wpercent'] / 100;
							$wis += $widthcols[$k]['miw'] - $curr;
							$wisa += $widthcols[$k]['miw'] - $curr;
						}
					}
				}

				// Now allocate surplus up to maximum width of each column
				$surplus = 0;
				$ttl = 0; // number of surplus columns

				if (!count($list)) {

					$wi = ($table['w'] - ($wis + $tblbw)); // i.e. extra space to distribute

					for ($k = 0; $k < $numcols; $k++) {

						$spareratio = ($table['l'][$k] / $totaltextlength); //  gives ratio to divide up free space

						// Don't allocate more than Maximum required width - save rest in surplus
						if ($widthcols[$k]['miw'] + ($wi * $spareratio) >= $widthcols[$k]['maw']) { // mPDF 5.7.3
							$surplus += ($wi * $spareratio) - ($widthcols[$k]['maw'] - $widthcols[$k]['miw']);
							$widthcols[$k]['miw'] = $widthcols[$k]['maw'];
						} else {
							$notsetlist[] = $k;
							$ttl += $table['l'][$k];
							$widthcols[$k]['miw'] += ($wi * $spareratio);
						}
					}

				} else {

					$wi = ($table['w'] - ($wis + $tblbw)); // i.e. extra space to distribute

					foreach ($list as $k) {

						$spareratio = ($table['l'][$k] / $totalatextlength); //  gives ratio to divide up free space

						// Don't allocate more than Maximum required width - save rest in surplus
						if ($widthcols[$k]['miw'] + ($wi * $spareratio) >= $widthcols[$k]['maw']) { // mPDF 5.7.3
							$surplus += ($wi * $spareratio) - ($widthcols[$k]['maw'] - $widthcols[$k]['miw']);
							$widthcols[$k]['miw'] = $widthcols[$k]['maw'];
						} else {
							$notsetlist[] = $k;
							$ttl += $table['l'][$k];
							$widthcols[$k]['miw'] += ($wi * $spareratio);
						}
					}
				}

				// If surplus still left over apportion it across columns
				if ($surplus) {

					if (count($notsetlist) && count($notsetlist) < $numcols) { // if some are set only add to remaining - otherwise add to all of them
						foreach ($notsetlist as $i) {
							if ($ttl) {
								$widthcols[$i]['miw'] += $surplus * $table['l'][$i] / $ttl;
							}
						}
					} elseif (count($list) && count($list) < $numcols) { // If some widths are defined, and others have been added up to their maxmum
						foreach ($list as $i) {
							$widthcols[$i]['miw'] += $surplus / count($list);
						}
					} elseif ($numcols) { // If all columns
						$ttl = array_sum($table['l']);
						if ($ttl) {
							for ($i = 0; $i < $numcols; $i++) {
								$widthcols[$i]['miw'] += $surplus * $table['l'][$i] / $ttl;
							}
						}
					}
				}
			}

			// This sets the columns all to minimum width (which has been increased above if appropriate)
			for ($i = 0; $i < $numcols; $i++) {
				$widthcols[$i] = $widthcols[$i]['miw'];
			}

			// TABLE NOT WIDE ENOUGH EVEN FOR MINIMUM CONTENT WIDTH
			// If sum of column widths set are too wide for table
			$checktablewidth = 0;
			for ($i = 0; $i < $numcols; $i++) {
				$checktablewidth += $widthcols[$i];
			}

			if ($checktablewidth > ($temppgwidth + 0.001 - $tblbw)) {

				$usedup = 0;
				$numleft = 0;

				for ($i = 0; $i < $numcols; $i++) {
					if ((isset($widthcols[$i]) && $widthcols[$i] > (($temppgwidth - $tblbw) / $numcols)) && (!isset($widthcols[$i]['w']))) {
						$numleft++;
						unset($widthcols[$i]);
					} else {
						$usedup += $widthcols[$i];
					}
				}

				for ($i = 0; $i < $numcols; $i++) {
					if (!isset($widthcols[$i]) || !$widthcols[$i]) {
						$widthcols[$i] = ((($temppgwidth - $tblbw) - $usedup) / ($numleft));
					}
				}
			}

		} else { // table has no width defined

			$table['w'] = $tablewidth;

			for ($i = 0; $i < $numcols; $i++) {

				if (isset($widthcols[$i]['wpercent']) && $this->keep_table_proportions) {
					$colwidth = $widthcols[$i]['maw'];
				} elseif (isset($widthcols[$i]['w'])) {
					$colwidth = $widthcols[$i]['miw'];
				} else {
					$colwidth = $widthcols[$i]['maw'];
				}

				unset($widthcols[$i]);
				$widthcols[$i] = $colwidth;

			}
		}

		if ($table['overflow'] === 'visible' && $table['level'] == 1) {

			if ($tablewidth > $this->blk[$this->blklvl]['inner_width']) {

				for ($j = 0; $j < $numcols; $j++) { // columns

					for ($i = 0; $i < $table['nr']; $i++) { // rows

						if (isset($table['cells'][$i][$j]) && $table['cells'][$i][$j]) {

							$colspan = (isset($table['cells'][$i][$j]['colspan']) ? $table['cells'][$i][$j]['colspan'] : 1);

							if ($colspan > 1) {
								$w = 0;

								for ($c = $j; $c < ($j + $colspan); $c++) {
									$w += $widthcols[$c];
								}

								if ($w > $this->blk[$this->blklvl]['inner_width']) {
									$diff = $w - ($this->blk[$this->blklvl]['inner_width'] - $tblbw);
									for ($c = $j; $c < ($j + $colspan); $c++) {
										$widthcols[$c] -= $diff * ($widthcols[$c] / $w);
									}
									$table['w'] -= $diff;
									$table['csp'][$j] = $w - $diff;
								}
							}
						}
					}
				}
			}

			$pgNo = 0;
			$currWc = 0;

			for ($i = 0; $i < $numcols; $i++) { // columns

				if (isset($table['csp'][$i])) {
					$w = $table['csp'][$i];
					unset($table['csp'][$i]);
				} else {
					$w = $widthcols[$i];
				}

				if (($currWc + $w + $tblbw) > $this->blk[$this->blklvl]['inner_width']) {
					$pgNo++;
					$currWc = $widthcols[$i];
				} else {
					$currWc += $widthcols[$i];
				}

				$table['colPg'][$i] = $pgNo;
			}
		}
	}

	function _tableHeight(&$table)
	{
		$level = $table['level'];
		$levelid = $table['levelid'];
		$cells = &$table['cells'];
		$numcols = $table['nc'];
		$numrows = $table['nr'];
		$listspan = [];
		$checkmaxheight = 0;
		$headerrowheight = 0;
		$checkmaxheightplus = 0;
		$headerrowheightplus = 0;
		$firstrowheight = 0;

		$footerrowheight = 0;
		$footerrowheightplus = 0;
		if ($this->table_rotate) {
			$temppgheight = $this->tbrot_maxh;
			$remainingpage = $this->tbrot_maxh;
		} else {
			$temppgheight = ($this->h - $this->bMargin - $this->tMargin) - $this->kwt_height;
			$remainingpage = ($this->h - $this->bMargin - $this->y) - $this->kwt_height;

			// If it is less than 1/20th of the remaining page height to finish the DIV (i.e. DIV padding + table bottom margin)
			// then allow for this
			$enddiv = $this->blk[$this->blklvl]['padding_bottom'] + $this->blk[$this->blklvl]['border_bottom']['w'] + $table['margin']['B'];
			if ($remainingpage > $enddiv && $enddiv / $remainingpage < 0.05) {
				$remainingpage -= $enddiv;
			} elseif ($remainingpage == 0) {
				$remainingpage = 0.001;
			}
			if ($temppgheight > $enddiv && $enddiv / $temppgheight < 0.05) {
				$temppgheight -= $enddiv;
			} elseif ($temppgheight == 0) {
				$temppgheight = 0.001;
			}
		}
		if ($remainingpage < 0) {
			$remainingpage = 0.001;
		}
		if ($temppgheight < 0) {
			$temppgheight = 0.001;
		}

		for ($i = 0; $i < $numrows; $i++) { // rows
			$heightrow = &$table['hr'][$i];
			for ($j = 0; $j < $numcols; $j++) { // columns
				if (isset($cells[$i][$j]) && $cells[$i][$j]) {
					$c = &$cells[$i][$j];

					if ($this->simpleTables) {
						if ($table['borders_separate']) { // NB twice border width
							$extraWLR = ($table['simple']['border_details']['L']['w'] + $table['simple']['border_details']['R']['w']) + ($c['padding']['L'] + $c['padding']['R']) + $table['border_spacing_H'];
							$extrh = ($table['simple']['border_details']['T']['w'] + $table['simple']['border_details']['B']['w']) + ($c['padding']['T'] + $c['padding']['B']) + $table['border_spacing_V'];
						} else {
							$extraWLR = ($table['simple']['border_details']['L']['w'] + $table['simple']['border_details']['R']['w']) / 2 + ($c['padding']['L'] + $c['padding']['R']);
							$extrh = ($table['simple']['border_details']['T']['w'] + $table['simple']['border_details']['B']['w']) / 2 + ($c['padding']['T'] + $c['padding']['B']);
						}
					} else {
						if ($this->packTableData) {
							list($bt, $br, $bb, $bl) = $this->_getBorderWidths($c['borderbin']);
						} else {
							$bt = $c['border_details']['T']['w'];
							$bb = $c['border_details']['B']['w'];
							$br = $c['border_details']['R']['w'];
							$bl = $c['border_details']['L']['w'];
						}
						if ($table['borders_separate']) { // NB twice border width
							$extraWLR = $bl + $br + $c['padding']['L'] + $c['padding']['R'] + $table['border_spacing_H'];
							$extrh = $bt + $bb + $c['padding']['T'] + $c['padding']['B'] + $table['border_spacing_V'];
						} else {
							$extraWLR = $bl / 2 + $br / 2 + $c['padding']['L'] + $c['padding']['R'];
							$extrh = $bt / 2 + $bb / 2 + $c['padding']['T'] + $c['padding']['B'];
						}
					}

					if ($table['overflow'] == 'visible' && $level == 1) {
						list($x, $cw) = $this->_splitTableGetWidth($table, $i, $j);
					} else {
						list($x, $cw) = $this->_tableGetWidth($table, $i, $j);
					}


					// Get CELL HEIGHT
					// ++ extra parameter forces wrap to break word
					if ($c['R'] && isset($c['textbuffer'])) {
						$str = '';
						foreach ($c['textbuffer'] as $t) {
							$str .= $t[0] . ' ';
						}
						$str = rtrim($str);
						$s_fs = $this->FontSizePt;
						$s_f = $this->FontFamily;
						$s_st = $this->FontStyle;
						$this->SetFont($c['textbuffer'][0][4], $c['textbuffer'][0][2], $c['textbuffer'][0][11] / $this->shrin_k, true, true);
						$tempch = $this->GetStringWidth($str, true, $c['textbuffer'][0][18], $c['textbuffer'][0][8]);
						if ($c['R'] >= 45 && $c['R'] < 90) {
							$tempch = ((sin(deg2rad($c['R']))) * $tempch ) + ((sin(deg2rad($c['R']))) * (($c['textbuffer'][0][11] / Mpdf::SCALE) / $this->shrin_k));
						}
						$this->SetFont($s_f, $s_st, $s_fs, true, true);
						$ch = ($tempch ) + $extrh;
					} else {
						if (isset($c['textbuffer']) && !empty($c['textbuffer'])) {
							$this->cellLineHeight = $c['cellLineHeight'];
							$this->cellLineStackingStrategy = $c['cellLineStackingStrategy'];
							$this->cellLineStackingShift = $c['cellLineStackingShift'];
							$this->divwidth = $cw - $extraWLR;
							$tempch = $this->printbuffer($c['textbuffer'], '', true, true);
						} else {
							$tempch = 0;
						}

						// Added cellpadding top and bottom. (Lineheight already adjusted)
						$ch = $tempch + $extrh;
					}
					// If height is defined and it is bigger than calculated $ch then update values
					if (isset($c['h']) && $c['h'] > $ch) {
						$c['mih'] = $ch; // in order to keep valign working
						$ch = $c['h'];
					} else {
						$c['mih'] = $ch;
					}
					if (isset($c['rowspan'])) {
						$listspan[] = [$i, $j];
					} elseif ($heightrow < $ch) {
						$heightrow = $ch;
					}

					// this is the extra used in _tableWrite to determine whether to trigger a page change
					if ($table['borders_separate']) {
						if ($i == ($numrows - 1) || (isset($c['rowspan']) && ($i + $c['rowspan']) == ($numrows))) {
							$extra = $table['margin']['B'] + $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
						} else {
							$extra = $table['border_spacing_V'] / 2;
						}
					} else {
						if (!$this->simpleTables) {
							$extra = $bb / 2;
						} elseif ($this->simpleTables) {
							$extra = $table['simple']['border_details']['B']['w'] / 2;
						}
					}
					if (isset($table['is_thead'][$i]) && $table['is_thead'][$i]) {
						if ($j == 0) {
							$headerrowheight += $ch;
							$headerrowheightplus += $ch + $extra;
						}
					} elseif (isset($table['is_tfoot'][$i]) && $table['is_tfoot'][$i]) {
						if ($j == 0) {
							$footerrowheight += $ch;
							$footerrowheightplus += $ch + $extra;
						}
					} else {
						$checkmaxheight = max($checkmaxheight, $ch);
						$checkmaxheightplus = max($checkmaxheightplus, $ch + $extra);
					}
					if ($this->tableLevel == 1 && $i == (isset($table['headernrows']) ? $table['headernrows'] : 0)) {
						$firstrowheight = max($ch, $firstrowheight);
					}
					unset($c);
				}
			}//end of columns
		}//end of rows

		$heightrow = &$table['hr'];
		foreach ($listspan as $span) {
			list($i, $j) = $span;
			$c = &$cells[$i][$j];
			$lr = $i + $c['rowspan'];
			if ($lr > $numrows) {
				$lr = $numrows;
			}
			$hs = $hsa = 0;
			$list = [];
			for ($k = $i; $k < $lr; $k++) {
				$hs += $heightrow[$k];
				// mPDF 6
				$sh = false; // specified height
				for ($m = 0; $m < $numcols; $m++) { // columns
					$tc = &$cells[$k][$m];
					if (isset($tc['rowspan'])) {
						continue;
					}
					if (isset($tc['h'])) {
						$sh = true;
						break;
					}
				}
				if (!$sh) {
					$list[] = $k;
				}
			}

			if ($table['borders_separate']) {
				if ($i == ($numrows - 1) || ($i + $c['rowspan']) == ($numrows)) {
					$extra = $table['margin']['B'] + $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
				} else {
					$extra = $table['border_spacing_V'] / 2;
				}
			} else {
				if (!$this->simpleTables) {
					if ($this->packTableData) {
						list($bt, $br, $bb, $bl) = $this->_getBorderWidths($c['borderbin']);
					} else {
						$bb = $c['border_details']['B']['w'];
					}
					$extra = $bb / 2;
				} elseif ($this->simpleTables) {
					$extra = $table['simple']['border_details']['B']['w'] / 2;
				}
			}
			if (!empty($table['is_thead'][$i])) {
				$headerrowheight = max($headerrowheight, $hs);
				$headerrowheightplus = max($headerrowheightplus, $hs + $extra);
			} elseif (!empty($table['is_tfoot'][$i])) {
				$footerrowheight = max($footerrowheight, $hs);
				$footerrowheightplus = max($footerrowheightplus, $hs + $extra);
			} else {
				$checkmaxheight = max($checkmaxheight, $hs);
				$checkmaxheightplus = max($checkmaxheightplus, $hs + $extra);
			}
			if ($this->tableLevel == 1 && $i == (isset($table['headernrows']) ? $table['headernrows'] : 0)) {
				$firstrowheight = max($hs, $firstrowheight);
			}

			if ($c['mih'] > $hs) {
				if (!$hs) {
					for ($k = $i; $k < $lr; $k++) {
						$heightrow[$k] = $c['mih'] / $c['rowspan'];
					}
				} elseif (!count($list)) { // no rows in the rowspan have a height specified, so share amongst all rows equally
					$hi = $c['mih'] - $hs;
					for ($k = $i; $k < $lr; $k++) {
						$heightrow[$k] += ($heightrow[$k] / $hs) * $hi;
					}
				} else {
					$hi = $c['mih'] - $hs; // mPDF 6
					foreach ($list as $k) {
						$heightrow[$k] += $hi / (count($list)); // mPDF 6
					}
				}
			}
			unset($c);

			// If rowspans overlap so that one or more rows do not have a height set...
			// i.e. for one or more rows, the only cells (explicit) in that row have rowspan>1
			// so heightrow is still == 0
			if ($heightrow[$i] == 0) {
				// Get row extent to analyse above and below
				$top = $i;
				foreach ($listspan as $checkspan) {
					list($cki, $ckj) = $checkspan;
					$c = &$cells[$cki][$ckj];
					if (isset($c['rowspan']) && $c['rowspan'] > 1) {
						if (($cki + $c['rowspan'] - 1) >= $i) {
							$top = min($top, $cki);
						}
					}
				}
				$bottom = $i + $c['rowspan'] - 1;
				// Check for overconstrained conditions
				for ($k = $top; $k <= $bottom; $k++) {
					// if ['hr'] for any of the others is also 0, then abort (too complicated)
					if ($k != $i && $heightrow[$k] == 0) {
						break(1);
					}
					// check again that top and bottom are not crossed by rowspans - or abort (too complicated)
					if ($k == $top) {
						// ???? take account of colspan as well???
						for ($m = 0; $m < $numcols; $m++) { // columns
							if (!isset($cells[$k][$m]) || $cells[$k][$m] == 0) {
								break(2);
							}
						}
					} elseif ($k == $bottom) {
						// ???? take account of colspan as well???
						for ($m = 0; $m < $numcols; $m++) { // columns
							$c = &$cells[$k][$m];
							if (isset($c['rowspan']) && $c['rowspan'] > 1) {
								break(2);
							}
						}
					}
				}
				// By columns add up col height using ['h'] if set or ['mih'] if not
				// Intentionally do not substract border-spacing
				$colH = [];
				$extH = 0;
				$newhr = [];
				for ($m = 0; $m < $numcols; $m++) { // columns
					for ($k = $top; $k <= $bottom; $k++) {
						if (isset($cells[$k][$m]) && $cells[$k][$m] != 0) {
							$c = &$cells[$k][$m];
							if (isset($c['h']) && $c['h']) {
								$useh = $c['h'];
							} // ???? take account of colspan as well???
							else {
								$useh = $c['mih'];
							}
							if (isset($colH[$m])) {
								$colH[$m] += $useh;
							} else {
								$colH[$m] = $useh;
							}
							if (!isset($c['rowspan']) || $c['rowspan'] < 2) {
								$newhr[$k] = max((isset($newhr[$k]) ? $newhr[$k] : 0), $useh);
							}
						}
					}
					$extH = max($extH, $colH[$m]); // mPDF 6
				}
				$newhr[$i] = $extH - array_sum($newhr);
				for ($k = $top; $k <= $bottom; $k++) {
					$heightrow[$k] = $newhr[$k];
				}
			}


			unset($c);
		}

		$table['h'] = array_sum($heightrow);
		unset($heightrow);

		if ($table['borders_separate']) {
			$table['h'] += $table['margin']['T'] + $table['margin']['B'] + $table['border_details']['T']['w'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] + $table['padding']['T'] + $table['padding']['B'];
		} else {
			$table['h'] += $table['margin']['T'] + $table['margin']['B'] + $table['max_cell_border_width']['T'] / 2 + $table['max_cell_border_width']['B'] / 2;
		}

		$maxrowheight = $checkmaxheightplus + $headerrowheightplus + $footerrowheightplus;
		$maxfirstrowheight = $firstrowheight + $headerrowheightplus + $footerrowheightplus; // includes thead, 1st row and tfoot
		return [$table['h'], $maxrowheight, $temppgheight, $remainingpage, $maxfirstrowheight];
	}

	function _tableGetWidth(&$table, $i, $j)
	{
		$cell = &$table['cells'][$i][$j];
		if ($cell) {
			if (isset($cell['x0'])) {
				return [$cell['x0'], $cell['w0']];
			}
			$x = 0;
			$widthcols = &$table['wc'];
			for ($k = 0; $k < $j; $k++) {
				$x += $widthcols[$k];
			}
			$w = $widthcols[$j];
			if (isset($cell['colspan'])) {
				for ($k = $j + $cell['colspan'] - 1; $k > $j; $k--) {
					$w += $widthcols[$k];
				}
			}
			$cell['x0'] = $x;
			$cell['w0'] = $w;
			return [$x, $w];
		}
		return [0, 0];
	}

	function _splitTableGetWidth(&$table, $i, $j)
	{
		$cell = &$table['cells'][$i][$j];
		if ($cell) {
			if (isset($cell['x0'])) {
				return [$cell['x0'], $cell['w0']];
			}
			$x = 0;
			$widthcols = &$table['wc'];
			$pg = $table['colPg'][$j];
			for ($k = 0; $k < $j; $k++) {
				if ($table['colPg'][$k] == $pg) {
					$x += $widthcols[$k];
				}
			}
			$w = $widthcols[$j];
			if (isset($cell['colspan'])) {
				for ($k = $j + $cell['colspan'] - 1; $k > $j; $k--) {
					if ($table['colPg'][$k] == $pg) {
						$w += $widthcols[$k];
					}
				}
			}
			$cell['x0'] = $x;
			$cell['w0'] = $w;
			return [$x, $w];
		}
		return [0, 0];
	}

	function _tableGetHeight(&$table, $i, $j)
	{
		$cell = &$table['cells'][$i][$j];
		if ($cell) {
			if (isset($cell['y0'])) {
				return [$cell['y0'], $cell['h0']];
			}
			$y = 0;
			$heightrow = &$table['hr'];
			for ($k = 0; $k < $i; $k++) {
				$y += $heightrow[$k];
			}
			$h = $heightrow[$i];
			if (isset($cell['rowspan'])) {
				for ($k = $i + $cell['rowspan'] - 1; $k > $i; $k--) {
					$h += $heightrow[$k];
				}
			}
			$cell['y0'] = $y;
			$cell['h0'] = $h;
			return [$y, $h];
		}
		return [0, 0];
	}

	function _tableGetMaxRowHeight($table, $row)
	{
		if ($row == $table['nc'] - 1) {
			return $table['hr'][$row];
		}
		$maxrowheight = $table['hr'][$row];
		for ($i = $row + 1; $i < $table['nr']; $i++) {
			$cellsset = 0;
			for ($j = 0; $j < $table['nc']; $j++) {
				if ($table['cells'][$i][$j]) {
					if (isset($table['cells'][$i][$j]['colspan'])) {
						$cellsset += $table['cells'][$i][$j]['colspan'];
					} else {
						$cellsset += 1;
					}
				}
			}
			if ($cellsset == $table['nc']) {
				return $maxrowheight;
			} else {
				$maxrowheight += $table['hr'][$i];
			}
		}
		return $maxrowheight;
	}

	// CHANGED TO ALLOW TABLE BORDER TO BE SPECIFIED CORRECTLY - added border_details
	function _tableRect($x, $y, $w, $h, $bord = -1, $details = [], $buffer = false, $bSeparate = false, $cort = 'cell', $tablecorner = '', $bsv = 0, $bsh = 0)
	{
		$cellBorderOverlay = [];

		if ($bord == -1) {
			$this->Rect($x, $y, $w, $h);
		} elseif ($this->simpleTables && ($cort == 'cell')) {
			$this->SetLineWidth($details['L']['w']);
			if ($details['L']['c']) {
				$this->SetDColor($details['L']['c']);
			} else {
				$this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
			}
			$this->SetLineJoin(0);
			$this->Rect($x, $y, $w, $h);
		} elseif ($bord) {
			if (!$bSeparate && $buffer) {
				$priority = 'LRTB';
				for ($p = 0; $p < strlen($priority); $p++) {
					$side = $priority[$p];
					$details['p'] = $side;

					$dom = 0;
					if (isset($details[$side]['w'])) {
						$dom += ($details[$side]['w'] * 100000);
					}
					if (isset($details[$side]['style'])) {
						$dom += (array_search($details[$side]['style'], $this->borderstyles) * 100);
					}
					if (isset($details[$side]['dom'])) {
						$dom += ($details[$side]['dom'] * 10);
					}

					// Precedence to darker colours at joins
					$coldom = 0;
					if (isset($details[$side]['c']) && is_array($details[$side]['c'])) {
						if ($details[$side]['c']{0} == 3) {  // RGB
							$coldom = 10 - (((ord($details[$side]['c']{1}) * 1.00) + (ord($details[$side]['c']{2}) * 1.00) + (ord($details[$side]['c']{3}) * 1.00)) / 76.5);
						}
					} // 10 black - 0 white
					if ($coldom) {
						$dom += $coldom;
					}
					// Lastly precedence to RIGHT and BOTTOM cells at joins
					if (isset($details['cellposdom'])) {
						$dom += $details['cellposdom'];
					}

					$save = false;
					if ($side == 'T' && $this->issetBorder($bord, Border::TOP)) {
						$cbord = Border::TOP;
						$save = true;
					} elseif ($side == 'L' && $this->issetBorder($bord, Border::LEFT)) {
						$cbord = Border::LEFT;
						$save = true;
					} elseif ($side == 'R' && $this->issetBorder($bord, Border::RIGHT)) {
						$cbord = Border::RIGHT;
						$save = true;
					} elseif ($side == 'B' && $this->issetBorder($bord, Border::BOTTOM)) {
						$cbord = Border::BOTTOM;
						$save = true;
					}

					if ($save) {
						$this->cellBorderBuffer[] = pack("A16nCnda6A10d14", str_pad(sprintf("%08.7f", $dom), 16, "0", STR_PAD_LEFT), $cbord, ord($side), $details[$side]['s'], $details[$side]['w'], $details[$side]['c'], $details[$side]['style'], $x, $y, $w, $h, $details['mbw']['BL'], $details['mbw']['BR'], $details['mbw']['RT'], $details['mbw']['RB'], $details['mbw']['TL'], $details['mbw']['TR'], $details['mbw']['LT'], $details['mbw']['LB'], $details['cellposdom'], 0);
						if ($details[$side]['style'] == 'ridge' || $details[$side]['style'] == 'groove' || $details[$side]['style'] == 'inset' || $details[$side]['style'] == 'outset' || $details[$side]['style'] == 'double') {
							$details[$side]['overlay'] = true;
							$this->cellBorderBuffer[] = pack("A16nCnda6A10d14", str_pad(sprintf("%08.7f", ($dom + 4)), 16, "0", STR_PAD_LEFT), $cbord, ord($side), $details[$side]['s'], $details[$side]['w'], $details[$side]['c'], $details[$side]['style'], $x, $y, $w, $h, $details['mbw']['BL'], $details['mbw']['BR'], $details['mbw']['RT'], $details['mbw']['RB'], $details['mbw']['TL'], $details['mbw']['TR'], $details['mbw']['LT'], $details['mbw']['LB'], $details['cellposdom'], 1);
						}
					}
				}
				return;
			}

			if (isset($details['p']) && strlen($details['p']) > 1) {
				$priority = $details['p'];
			} else {
				$priority = 'LTRB';
			}
			$Tw = 0;
			$Rw = 0;
			$Bw = 0;
			$Lw = 0;
			if (isset($details['T']['w'])) {
				$Tw = $details['T']['w'];
			}
			if (isset($details['R']['w'])) {
				$Rw = $details['R']['w'];
			}
			if (isset($details['B']['w'])) {
				$Bw = $details['B']['w'];
			}
			if (isset($details['L']['w'])) {
				$Lw = $details['L']['w'];
			}

			$x2 = $x + $w;
			$y2 = $y + $h;
			$oldlinewidth = $this->LineWidth;

			for ($p = 0; $p < strlen($priority); $p++) {
				$side = $priority[$p];
				$xadj = 0;
				$xadj2 = 0;
				$yadj = 0;
				$yadj2 = 0;
				$print = false;
				if ($Tw && $side == 'T' && $this->issetBorder($bord, Border::TOP)) { // TOP
					$ly1 = $y;
					$ly2 = $y;
					$lx1 = $x;
					$lx2 = $x2;
					$this->SetLineWidth($Tw);
					if ($cort == 'cell' || strpos($tablecorner, 'L') !== false) {
						if ($Tw > $Lw) {
							$xadj = ($Tw - $Lw) / 2;
						}
						if ($Tw < $Lw) {
							$xadj = ($Tw + $Lw) / 2;
						}
					} else {
						$xadj = $Tw / 2 - $bsh / 2;
					}
					if ($cort == 'cell' || strpos($tablecorner, 'R') !== false) {
						if ($Tw > $Rw) {
							$xadj2 = ($Tw - $Rw) / 2;
						}
						if ($Tw < $Rw) {
							$xadj2 = ($Tw + $Rw) / 2;
						}
					} else {
						$xadj2 = $Tw / 2 - $bsh / 2;
					}
					if (!$bSeparate && !empty($details['mbw']) && !empty($details['mbw']['TL'])) {
						$xadj = ($Tw - $details['mbw']['TL']) / 2;
					}
					if (!$bSeparate && !empty($details['mbw']) && !empty($details['mbw']['TR'])) {
						$xadj2 = ($Tw - $details['mbw']['TR']) / 2;
					}
					$print = true;
				}
				if ($Lw && $side == 'L' && $this->issetBorder($bord, Border::LEFT)) { // LEFT
					$ly1 = $y;
					$ly2 = $y2;
					$lx1 = $x;
					$lx2 = $x;
					$this->SetLineWidth($Lw);
					if ($cort == 'cell' || strpos($tablecorner, 'T') !== false) {
						if ($Lw > $Tw) {
							$yadj = ($Lw - $Tw) / 2;
						}
						if ($Lw < $Tw) {
							$yadj = ($Lw + $Tw) / 2;
						}
					} else {
						$yadj = $Lw / 2 - $bsv / 2;
					}
					if ($cort == 'cell' || strpos($tablecorner, 'B') !== false) {
						if ($Lw > $Bw) {
							$yadj2 = ($Lw - $Bw) / 2;
						}
						if ($Lw < $Bw) {
							$yadj2 = ($Lw + $Bw) / 2;
						}
					} else {
						$yadj2 = $Lw / 2 - $bsv / 2;
					}
					if (!$bSeparate && $details['mbw']['LT']) {
						$yadj = ($Lw - $details['mbw']['LT']) / 2;
					}
					if (!$bSeparate && $details['mbw']['LB']) {
						$yadj2 = ($Lw - $details['mbw']['LB']) / 2;
					}
					$print = true;
				}
				if ($Rw && $side == 'R' && $this->issetBorder($bord, Border::RIGHT)) { // RIGHT
					$ly1 = $y;
					$ly2 = $y2;
					$lx1 = $x2;
					$lx2 = $x2;
					$this->SetLineWidth($Rw);
					if ($cort == 'cell' || strpos($tablecorner, 'T') !== false) {
						if ($Rw < $Tw) {
							$yadj = ($Rw + $Tw) / 2;
						}
						if ($Rw > $Tw) {
							$yadj = ($Rw - $Tw) / 2;
						}
					} else {
						$yadj = $Rw / 2 - $bsv / 2;
					}

					if ($cort == 'cell' || strpos($tablecorner, 'B') !== false) {
						if ($Rw > $Bw) {
							$yadj2 = ($Rw - $Bw) / 2;
						}
						if ($Rw < $Bw) {
							$yadj2 = ($Rw + $Bw) / 2;
						}
					} else {
						$yadj2 = $Rw / 2 - $bsv / 2;
					}

					if (!$bSeparate && !empty($details['mbw']) && !empty($details['mbw']['RT'])) {
						$yadj = ($Rw - $details['mbw']['RT']) / 2;
					}
					if (!$bSeparate && !empty($details['mbw']) && !empty($details['mbw']['RB'])) {
						$yadj2 = ($Rw - $details['mbw']['RB']) / 2;
					}
					$print = true;
				}
				if ($Bw && $side == 'B' && $this->issetBorder($bord, Border::BOTTOM)) { // BOTTOM
					$ly1 = $y2;
					$ly2 = $y2;
					$lx1 = $x;
					$lx2 = $x2;
					$this->SetLineWidth($Bw);
					if ($cort == 'cell' || strpos($tablecorner, 'L') !== false) {
						if ($Bw > $Lw) {
							$xadj = ($Bw - $Lw) / 2;
						}
						if ($Bw < $Lw) {
							$xadj = ($Bw + $Lw) / 2;
						}
					} else {
						$xadj = $Bw / 2 - $bsh / 2;
					}
					if ($cort == 'cell' || strpos($tablecorner, 'R') !== false) {
						if ($Bw > $Rw) {
							$xadj2 = ($Bw - $Rw) / 2;
						}
						if ($Bw < $Rw) {
							$xadj2 = ($Bw + $Rw) / 2;
						}
					} else {
						$xadj2 = $Bw / 2 - $bsh / 2;
					}
					if (!$bSeparate && isset($details['mbw']) && isset($details['mbw']['BL'])) {
						$xadj = ($Bw - $details['mbw']['BL']) / 2;
					}
					if (!$bSeparate && isset($details['mbw']) && isset($details['mbw']['BR'])) {
						$xadj2 = ($Bw - $details['mbw']['BR']) / 2;
					}
					$print = true;
				}

				// Now draw line
				if ($print) {
					/* -- TABLES-ADVANCED-BORDERS -- */
					if ($details[$side]['style'] == 'double') {
						if (!isset($details[$side]['overlay']) || !$details[$side]['overlay'] || $bSeparate) {
							if ($details[$side]['c']) {
								$this->SetDColor($details[$side]['c']);
							} else {
								$this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
							}
							$this->Line($lx1 + $xadj, $ly1 + $yadj, $lx2 - $xadj2, $ly2 - $yadj2);
						}
						if ((isset($details[$side]['overlay']) && $details[$side]['overlay']) || $bSeparate) {
							if ($bSeparate && $cort == 'table') {
								if ($side == 'T') {
									$xadj -= $this->LineWidth / 2;
									$xadj2 -= $this->LineWidth;
									if ($this->issetBorder($bord, Border::LEFT)) {
										$xadj += $this->LineWidth / 2;
									}
									if ($this->issetBorder($bord, Border::RIGHT)) {
										$xadj2 += $this->LineWidth;
									}
								}
								if ($side == 'L') {
									$yadj -= $this->LineWidth / 2;
									$yadj2 -= $this->LineWidth;
									if ($this->issetBorder($bord, Border::TOP)) {
										$yadj += $this->LineWidth / 2;
									}
									if ($this->issetBorder($bord, Border::BOTTOM)) {
										$yadj2 += $this->LineWidth;
									}
								}
								if ($side == 'B') {
									$xadj -= $this->LineWidth / 2;
									$xadj2 -= $this->LineWidth;
									if ($this->issetBorder($bord, Border::LEFT)) {
										$xadj += $this->LineWidth / 2;
									}
									if ($this->issetBorder($bord, Border::RIGHT)) {
										$xadj2 += $this->LineWidth;
									}
								}
								if ($side == 'R') {
									$yadj -= $this->LineWidth / 2;
									$yadj2 -= $this->LineWidth;
									if ($this->issetBorder($bord, Border::TOP)) {
										$yadj += $this->LineWidth / 2;
									}
									if ($this->issetBorder($bord, Border::BOTTOM)) {
										$yadj2 += $this->LineWidth;
									}
								}
							}

							$this->SetLineWidth($this->LineWidth / 3);

							$tbcol = $this->colorConverter->convert(255, $this->PDFAXwarnings);
							for ($l = 0; $l <= $this->blklvl; $l++) {
								if ($this->blk[$l]['bgcolor']) {
									$tbcol = ($this->blk[$l]['bgcolorarray']);
								}
							}

							if ($bSeparate) {
								$cellBorderOverlay[] = [
									'x' => $lx1 + $xadj,
									'y' => $ly1 + $yadj,
									'x2' => $lx2 - $xadj2,
									'y2' => $ly2 - $yadj2,
									'col' => $tbcol,
									'lw' => $this->LineWidth,
								];
							} else {
								$this->SetDColor($tbcol);
								$this->Line($lx1 + $xadj, $ly1 + $yadj, $lx2 - $xadj2, $ly2 - $yadj2);
							}
						}
					} elseif (isset($details[$side]['style']) && ($details[$side]['style'] == 'ridge' || $details[$side]['style'] == 'groove' || $details[$side]['style'] == 'inset' || $details[$side]['style'] == 'outset')) {
						if (!isset($details[$side]['overlay']) || !$details[$side]['overlay'] || $bSeparate) {
							if ($details[$side]['c']) {
								$this->SetDColor($details[$side]['c']);
							} else {
								$this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
							}
							if ($details[$side]['style'] == 'outset' || $details[$side]['style'] == 'groove') {
								$nc = $this->colorConverter->darken($details[$side]['c']);
								$this->SetDColor($nc);
							} elseif ($details[$side]['style'] == 'ridge' || $details[$side]['style'] == 'inset') {
								$nc = $this->colorConverter->lighten($details[$side]['c']);
								$this->SetDColor($nc);
							}
							$this->Line($lx1 + $xadj, $ly1 + $yadj, $lx2 - $xadj2, $ly2 - $yadj2);
						}
						if ((isset($details[$side]['overlay']) && $details[$side]['overlay']) || $bSeparate) {
							if ($details[$side]['c']) {
								$this->SetDColor($details[$side]['c']);
							} else {
								$this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
							}
							$doubleadj = ($this->LineWidth) / 3;
							$this->SetLineWidth($this->LineWidth / 2);
							$xadj3 = $yadj3 = $wadj3 = $hadj3 = 0;

							if ($details[$side]['style'] == 'ridge' || $details[$side]['style'] == 'inset') {
								$nc = $this->colorConverter->darken($details[$side]['c']);

								if ($bSeparate && $cort == 'table') {
									if ($side == 'T') {
										$yadj3 = $this->LineWidth / 2;
										$xadj3 = -$this->LineWidth / 2;
										$wadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::LEFT)) {
											$xadj3 += $this->LineWidth;
											$wadj3 -= $this->LineWidth;
										}
										if ($this->issetBorder($bord, Border::RIGHT)) {
											$wadj3 -= $this->LineWidth * 2;
										}
									}
									if ($side == 'L') {
										$xadj3 = $this->LineWidth / 2;
										$yadj3 = -$this->LineWidth / 2;
										$hadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::TOP)) {
											$yadj3 += $this->LineWidth;
											$hadj3 -= $this->LineWidth;
										}
										if ($this->issetBorder($bord, Border::BOTTOM)) {
											$hadj3 -= $this->LineWidth * 2;
										}
									}
									if ($side == 'B') {
										$yadj3 = $this->LineWidth / 2;
										$xadj3 = -$this->LineWidth / 2;
										$wadj3 = $this->LineWidth;
									}
									if ($side == 'R') {
										$xadj3 = $this->LineWidth / 2;
										$yadj3 = -$this->LineWidth / 2;
										$hadj3 = $this->LineWidth;
									}
								} elseif ($side == 'T') {
									$yadj3 = $this->LineWidth / 2;
									$xadj3 = $this->LineWidth / 2;
									$wadj3 = -$this->LineWidth * 2;
								} elseif ($side == 'L') {
									$xadj3 = $this->LineWidth / 2;
									$yadj3 = $this->LineWidth / 2;
									$hadj3 = -$this->LineWidth * 2;
								} elseif ($side == 'B' && $bSeparate) {
									$yadj3 = $this->LineWidth / 2;
									$wadj3 = $this->LineWidth / 2;
								} elseif ($side == 'R' && $bSeparate) {
									$xadj3 = $this->LineWidth / 2;
									$hadj3 = $this->LineWidth / 2;
								} elseif ($side == 'B') {
									$yadj3 = $this->LineWidth / 2;
									$xadj3 = $this->LineWidth / 2;
								} elseif ($side == 'R') {
									$xadj3 = $this->LineWidth / 2;
									$yadj3 = $this->LineWidth / 2;
								}
							} else {
								$nc = $this->colorConverter->lighten($details[$side]['c']);

								if ($bSeparate && $cort == 'table') {
									if ($side == 'T') {
										$yadj3 = $this->LineWidth / 2;
										$xadj3 = -$this->LineWidth / 2;
										$wadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::LEFT)) {
											$xadj3 += $this->LineWidth;
											$wadj3 -= $this->LineWidth;
										}
									}
									if ($side == 'L') {
										$xadj3 = $this->LineWidth / 2;
										$yadj3 = -$this->LineWidth / 2;
										$hadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::TOP)) {
											$yadj3 += $this->LineWidth;
											$hadj3 -= $this->LineWidth;
										}
									}
									if ($side == 'B') {
										$yadj3 = $this->LineWidth / 2;
										$xadj3 = -$this->LineWidth / 2;
										$wadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::LEFT)) {
											$xadj3 += $this->LineWidth;
											$wadj3 -= $this->LineWidth;
										}
									}
									if ($side == 'R') {
										$xadj3 = $this->LineWidth / 2;
										$yadj3 = -$this->LineWidth / 2;
										$hadj3 = $this->LineWidth;
										if ($this->issetBorder($bord, Border::TOP)) {
											$yadj3 += $this->LineWidth;
											$hadj3 -= $this->LineWidth;
										}
									}
								} elseif ($side == 'T') {
									$yadj3 = $this->LineWidth / 2;
									$xadj3 = $this->LineWidth / 2;
								} elseif ($side == 'L') {
									$xadj3 = $this->LineWidth / 2;
									$yadj3 = $this->LineWidth / 2;
								} elseif ($side == 'B' && $bSeparate) {
									$yadj3 = $this->LineWidth / 2;
									$xadj3 = $this->LineWidth / 2;
								} elseif ($side == 'R' && $bSeparate) {
									$xadj3 = $this->LineWidth / 2;
									$yadj3 = $this->LineWidth / 2;
								} elseif ($side == 'B') {
									$yadj3 = $this->LineWidth / 2;
									$xadj3 = -$this->LineWidth / 2;
									$wadj3 = $this->LineWidth;
								} elseif ($side == 'R') {
									$xadj3 = $this->LineWidth / 2;
									$yadj3 = -$this->LineWidth / 2;
									$hadj3 = $this->LineWidth;
								}
							}

							if ($bSeparate) {
								$cellBorderOverlay[] = [
									'x' => $lx1 + $xadj + $xadj3,
									'y' => $ly1 + $yadj + $yadj3,
									'x2' => $lx2 - $xadj2 + $xadj3 + $wadj3,
									'y2' => $ly2 - $yadj2 + $yadj3 + $hadj3,
									'col' => $nc,
									'lw' => $this->LineWidth,
								];
							} else {
								$this->SetDColor($nc);
								$this->Line($lx1 + $xadj + $xadj3, $ly1 + $yadj + $yadj3, $lx2 - $xadj2 + $xadj3 + $wadj3, $ly2 - $yadj2 + $yadj3 + $hadj3);
							}
						}
					} else {
						/* -- END TABLES-ADVANCED-BORDERS -- */
						if ($details[$side]['style'] == 'dashed') {
							$dashsize = 2; // final dash will be this + 1*linewidth
							$dashsizek = 1.5; // ratio of Dash/Blank
							$this->SetDash($dashsize, ($dashsize / $dashsizek) + ($this->LineWidth * 2));
						} elseif ($details[$side]['style'] == 'dotted') {
							$this->SetLineJoin(1);
							$this->SetLineCap(1);
							$this->SetDash(0.001, ($this->LineWidth * 2));
						}
						if ($details[$side]['c']) {
							$this->SetDColor($details[$side]['c']);
						} else {
							$this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
						}
						$this->Line($lx1 + $xadj, $ly1 + $yadj, $lx2 - $xadj2, $ly2 - $yadj2);
						/* -- TABLES-ADVANCED-BORDERS -- */
					}
					/* -- END TABLES-ADVANCED-BORDERS -- */

					// Reset Corners
					$this->SetDash();
					// BUTT style line cap
					$this->SetLineCap(2);
				}
			}

			if ($bSeparate && count($cellBorderOverlay)) {
				foreach ($cellBorderOverlay as $cbo) {
					$this->SetLineWidth($cbo['lw']);
					$this->SetDColor($cbo['col']);
					$this->Line($cbo['x'], $cbo['y'], $cbo['x2'], $cbo['y2']);
				}
			}

			// $this->SetLineWidth($oldlinewidth);
			// $this->SetDColor($this->colorConverter->convert(0, $this->PDFAXwarnings));
		}
	}

	/* -- TABLES -- */
	/* -- TABLES-ADVANCED-BORDERS -- */

	/* -- END TABLES-ADVANCED-BORDERS -- */

	function _table2cellBorder(&$tableb, &$cbdb, &$cellb, $bval)
	{
		if ($tableb && $tableb['w'] > $cbdb['w']) {
			$cbdb = $tableb;
			$this->setBorder($cellb, $bval);
		} elseif ($tableb && $tableb['w'] == $cbdb['w'] && array_search($tableb['style'], $this->borderstyles) > array_search($cbdb['style'], $this->borderstyles)) {
			$cbdb = $tableb;
			$this->setBorder($cellb, $bval);
		}
	}

	// FIX BORDERS ********************************************
	function _fixTableBorders(&$table)
	{
		if (!$table['borders_separate'] && $table['border_details']['L']['w']) {
			$table['max_cell_border_width']['L'] = $table['border_details']['L']['w'];
		}
		if (!$table['borders_separate'] && $table['border_details']['R']['w']) {
			$table['max_cell_border_width']['R'] = $table['border_details']['R']['w'];
		}
		if (!$table['borders_separate'] && $table['border_details']['T']['w']) {
			$table['max_cell_border_width']['T'] = $table['border_details']['T']['w'];
		}
		if (!$table['borders_separate'] && $table['border_details']['B']['w']) {
			$table['max_cell_border_width']['B'] = $table['border_details']['B']['w'];
		}
		if ($this->simpleTables) {
			return;
		}
		$cells = &$table['cells'];
		$numcols = $table['nc'];
		$numrows = $table['nr'];
		/* -- TABLES-ADVANCED-BORDERS -- */
		if (isset($table['topntail']) && $table['topntail']) {
			$tntborddet = $this->border_details($table['topntail']);
		}
		if (isset($table['thead-underline']) && $table['thead-underline']) {
			$thuborddet = $this->border_details($table['thead-underline']);
		}
		/* -- END TABLES-ADVANCED-BORDERS -- */

		for ($i = 0; $i < $numrows; $i++) { // Rows
			for ($j = 0; $j < $numcols; $j++) { // Columns
				if (isset($cells[$i][$j]) && $cells[$i][$j]) {
					$cell = &$cells[$i][$j];
					if ($this->packTableData) {
						$cbord = $this->_unpackCellBorder($cell['borderbin']);
					} else {
						$cbord = &$cells[$i][$j];
					}

					// mPDF 5.7.3
					if (!$cbord['border'] && $cbord['border'] !== 0 && isset($table['border']) && $table['border'] && $this->table_border_attr_set) {
						$cbord['border'] = $table['border'];
						$cbord['border_details'] = $table['border_details'];
					}

					if (isset($cell['colspan']) && $cell['colspan'] > 1) {
						$ccolsp = $cell['colspan'];
					} else {
						$ccolsp = 1;
					}
					if (isset($cell['rowspan']) && $cell['rowspan'] > 1) {
						$crowsp = $cell['rowspan'];
					} else {
						$crowsp = 1;
					}

					$cbord['border_details']['cellposdom'] = ((($i + 1) / $numrows) / 10000 ) + ((($j + 1) / $numcols) / 10 );
					// Inherit Cell border from Table border
					if ($this->table_border_css_set && !$table['borders_separate']) {
						if ($i == 0) {
							$this->_table2cellBorder($table['border_details']['T'], $cbord['border_details']['T'], $cbord['border'], Border::TOP);
						}
						if ($i == ($numrows - 1) || ($i + $crowsp) == ($numrows)) {
							$this->_table2cellBorder($table['border_details']['B'], $cbord['border_details']['B'], $cbord['border'], Border::BOTTOM);
						}
						if ($j == 0) {
							$this->_table2cellBorder($table['border_details']['L'], $cbord['border_details']['L'], $cbord['border'], Border::LEFT);
						}
						if ($j == ($numcols - 1) || ($j + $ccolsp) == ($numcols)) {
							$this->_table2cellBorder($table['border_details']['R'], $cbord['border_details']['R'], $cbord['border'], Border::RIGHT);
						}
					}

					/* -- TABLES-ADVANCED-BORDERS -- */
					$fixbottom = true;
					if (isset($table['topntail']) && $table['topntail']) {
						if ($i == 0) {
							$cbord['border_details']['T'] = $tntborddet;
							$this->setBorder($cbord['border'], Border::TOP);
						}
						if ($this->tableLevel == 1 && $table['headernrows'] > 0 && $i == $table['headernrows'] - 1) {
							$cbord['border_details']['B'] = $tntborddet;
							$this->setBorder($cbord['border'], Border::BOTTOM);
							$fixbottom = false;
						} elseif ($this->tableLevel == 1 && $table['headernrows'] > 0 && $i == $table['headernrows']) {
							if (!$table['borders_separate']) {
								$cbord['border_details']['T'] = $tntborddet;
								$this->setBorder($cbord['border'], Border::TOP);
							}
						}
						if ($this->tableLevel == 1 && $table['footernrows'] > 0 && $i == ($numrows - $table['footernrows'] - 1)) {
							if (!$table['borders_separate']) {
								$cbord['border_details']['B'] = $tntborddet;
								$this->setBorder($cbord['border'], Border::BOTTOM);
								$fixbottom = false;
							}
						} elseif ($this->tableLevel == 1 && $table['footernrows'] > 0 && $i == ($numrows - $table['footernrows'])) {
							$cbord['border_details']['T'] = $tntborddet;
							$this->setBorder($cbord['border'], Border::TOP);
						}
						if ($this->tabletheadjustfinished) { // $this->tabletheadjustfinished called from tableheader
							if (!$table['borders_separate']) {
								$cbord['border_details']['T'] = $tntborddet;
								$this->setBorder($cbord['border'], Border::TOP);
							}
						}
						if ($i == ($numrows - 1) || ($i + $crowsp) == ($numrows)) {
							$cbord['border_details']['B'] = $tntborddet;
							$this->setBorder($cbord['border'], Border::BOTTOM);
						}
					}
					if (isset($table['thead-underline']) && $table['thead-underline']) {
						if ($table['borders_separate']) {
							if ($i == 0) {
								$cbord['border_details']['B'] = $thuborddet;
								$this->setBorder($cbord['border'], Border::BOTTOM);
								$fixbottom = false;
							}
						} else {
							if ($this->tableLevel == 1 && $table['headernrows'] > 0 && $i == $table['headernrows'] - 1) {
								$cbord['border_details']['T'] = $thuborddet;
								$this->setBorder($cbord['border'], Border::TOP);
							} elseif ($this->tabletheadjustfinished) { // $this->tabletheadjustfinished called from tableheader
								$cbord['border_details']['T'] = $thuborddet;
								$this->setBorder($cbord['border'], Border::TOP);
							}
						}
					}

					// Collapse Border - Algorithm for conflicting borders
					// Hidden >> Width >> double>solid>dashed>dotted... >> style set on cell>table >> top/left>bottom/right
					// Do not turn off border which is overridden
					// Needed for page break for TOP/BOTTOM both to be defined in Collapsed borders
					// Means it is painted twice. (Left/Right can still disable overridden border)
					if (!$table['borders_separate']) {

						if (($i < ($numrows - 1) || ($i + $crowsp) < $numrows ) && $fixbottom) { // Bottom

							for ($cspi = 0; $cspi < $ccolsp; $cspi++) {

								// already defined Top for adjacent cell below
								if (isset($cells[($i + $crowsp)][$j + $cspi])) {
									if ($this->packTableData) {
										$adjc = $cells[($i + $crowsp)][$j + $cspi];
										$celladj = $this->_unpackCellBorder($adjc['borderbin']);
									} else {
										$celladj = & $cells[($i + $crowsp)][$j + $cspi];
									}
								} else {
									$celladj = false;
								}

								if ($celladj && $celladj['border_details']['T']['s'] == 1) {

									$csadj = $celladj['border_details']['T']['w'];
									$csthis = $cbord['border_details']['B']['w'];

									// Hidden
									if ($cbord['border_details']['B']['style'] == 'hidden') {

										$celladj['border_details']['T'] = $cbord['border_details']['B'];
										$this->setBorder($celladj['border'], Border::TOP, false);
										$this->setBorder($cbord['border'], Border::BOTTOM, false);

									} elseif ($celladj['border_details']['T']['style'] == 'hidden') {

										$cbord['border_details']['B'] = $celladj['border_details']['T'];
										$this->setBorder($cbord['border'], Border::BOTTOM, false);
										$this->setBorder($celladj['border'], Border::TOP, false);

									} elseif ($csthis > $csadj) { // Width

										if (!isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) || (isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) && $cells[($i + $crowsp)][$j + $cspi]['colspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['T'] = $cbord['border_details']['B'];
											$this->setBorder($cbord['border'], Border::BOTTOM);
										}

									} elseif ($csadj > $csthis) {

										if ($ccolsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['B'] = $celladj['border_details']['T'];
											$this->setBorder($celladj['border'], Border::TOP);
										}

									} elseif (array_search($cbord['border_details']['B']['style'], $this->borderstyles) > array_search($celladj['border_details']['T']['style'], $this->borderstyles)) { // double>solid>dashed>dotted...

										if (!isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) || (isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) && $cells[($i + $crowsp)][$j + $cspi]['colspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['T'] = $cbord['border_details']['B'];
											$this->setBorder($cbord['border'], Border::BOTTOM);
										}

									} elseif (array_search($celladj['border_details']['T']['style'], $this->borderstyles) > array_search($cbord['border_details']['B']['style'], $this->borderstyles)) {

										if ($ccolsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['B'] = $celladj['border_details']['T'];
											$this->setBorder($celladj['border'], Border::TOP);
										}

									} elseif ($celladj['border_details']['T']['dom'] > $celladj['border_details']['B']['dom']) { // Style set on cell vs. table

										if ($ccolsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['B'] = $celladj['border_details']['T'];
											$this->setBorder($celladj['border'], Border::TOP);
										}

									} else { // Style set on cell vs. table  - OR - LEFT/TOP (cell) in preference to BOTTOM/RIGHT

										if (!isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) || (isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) && $cells[($i + $crowsp)][$j + $cspi]['colspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['T'] = $cbord['border_details']['B'];
											$this->setBorder($cbord['border'], Border::BOTTOM);
										}

									}

								} elseif ($celladj) {

									if (!isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) || (isset($cells[($i + $crowsp)][$j + $cspi]['colspan']) && $cells[($i + $crowsp)][$j + $cspi]['colspan'] < 2)) { // don't overwrite bordering cells that span
										$celladj['border_details']['T'] = $cbord['border_details']['B'];
									}

								}

								// mPDF 5.7.4
								if ($celladj && $this->packTableData) {
									$cells[$i + $crowsp][$j + $cspi]['borderbin'] = $this->_packCellBorder($celladj);
								}

								unset($celladj);
							}
						}

						if ($j < ($numcols - 1) || ($j + $ccolsp) < $numcols) { // Right-Left

							for ($cspi = 0; $cspi < $crowsp; $cspi++) {

								// already defined Left for adjacent cell to R
								if (isset($cells[($i + $cspi)][$j + $ccolsp])) {
									if ($this->packTableData) {
										$adjc = $cells[($i + $cspi)][$j + $ccolsp];
										$celladj = $this->_unpackCellBorder($adjc['borderbin']);
									} else {
										$celladj = & $cells[$i + $cspi][$j + $ccolsp];
									}
								} else {
									$celladj = false;
								}
								if ($celladj && $celladj['border_details']['L']['s'] == 1) {
									$csadj = $celladj['border_details']['L']['w'];
									$csthis = $cbord['border_details']['R']['w'];
									// Hidden
									if ($cbord['border_details']['R']['style'] == 'hidden') {
										$celladj['border_details']['L'] = $cbord['border_details']['R'];
										$this->setBorder($celladj['border'], Border::LEFT, false);
										$this->setBorder($cbord['border'], Border::RIGHT, false);
									} elseif ($celladj['border_details']['L']['style'] == 'hidden') {
										$cbord['border_details']['R'] = $celladj['border_details']['L'];
										$this->setBorder($cbord['border'], Border::RIGHT, false);
										$this->setBorder($celladj['border'], Border::LEFT, false);
									} // Width
									elseif ($csthis > $csadj) {
										if (!isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) || (isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) && $cells[($i + $cspi)][$j + $ccolsp]['rowspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['L'] = $cbord['border_details']['R'];
											$this->setBorder($cbord['border'], Border::RIGHT);
											$this->setBorder($celladj['border'], Border::LEFT, false);
										}
									} elseif ($csadj > $csthis) {
										if ($crowsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['R'] = $celladj['border_details']['L'];
											$this->setBorder($cbord['border'], Border::RIGHT, false);
											$this->setBorder($celladj['border'], Border::LEFT);
										}
									} // double>solid>dashed>dotted...
									elseif (array_search($cbord['border_details']['R']['style'], $this->borderstyles) > array_search($celladj['border_details']['L']['style'], $this->borderstyles)) {
										if (!isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) || (isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) && $cells[($i + $cspi)][$j + $ccolsp]['rowspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['L'] = $cbord['border_details']['R'];
											$this->setBorder($celladj['border'], Border::LEFT, false);
											$this->setBorder($cbord['border'], Border::RIGHT);
										}
									} elseif (array_search($celladj['border_details']['L']['style'], $this->borderstyles) > array_search($cbord['border_details']['R']['style'], $this->borderstyles)) {
										if ($crowsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['R'] = $celladj['border_details']['L'];
											$this->setBorder($cbord['border'], Border::RIGHT, false);
											$this->setBorder($celladj['border'], Border::LEFT);
										}
									} // Style set on cell vs. table
									elseif ($celladj['border_details']['L']['dom'] > $cbord['border_details']['R']['dom']) {
										if ($crowsp < 2) { // don't overwrite this cell if it spans
											$cbord['border_details']['R'] = $celladj['border_details']['L'];
											$this->setBorder($celladj['border'], Border::LEFT);
										}
									} // Style set on cell vs. table  - OR - LEFT/TOP (cell) in preference to BOTTOM/RIGHT
									else {
										if (!isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) || (isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) && $cells[($i + $cspi)][$j + $ccolsp]['rowspan'] < 2)) { // don't overwrite bordering cells that span
											$celladj['border_details']['L'] = $cbord['border_details']['R'];
											$this->setBorder($cbord['border'], Border::RIGHT);
										}
									}
								} elseif ($celladj) {
									// if right-cell border is not set
									if (!isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) || (isset($cells[($i + $cspi)][$j + $ccolsp]['rowspan']) && $cells[($i + $cspi)][$j + $ccolsp]['rowspan'] < 2)) { // don't overwrite bordering cells that span
										$celladj['border_details']['L'] = $cbord['border_details']['R'];
									}
								}
								// mPDF 5.7.4
								if ($celladj && $this->packTableData) {
									$cells[$i + $cspi][$j + $ccolsp]['borderbin'] = $this->_packCellBorder($celladj);
								}
								unset($celladj);
							}
						}
					}


					// Set maximum cell border width meeting at LRTB edges of cell - used for extended cell border
					// ['border_details']['mbw']['LT'] = meeting border width - Left border - Top end
					if (!$table['borders_separate']) {
						$cbord['border_details']['mbw']['BL'] = max($cbord['border_details']['mbw']['BL'], $cbord['border_details']['L']['w']);
						$cbord['border_details']['mbw']['BR'] = max($cbord['border_details']['mbw']['BR'], $cbord['border_details']['R']['w']);
						$cbord['border_details']['mbw']['RT'] = max($cbord['border_details']['mbw']['RT'], $cbord['border_details']['T']['w']);
						$cbord['border_details']['mbw']['RB'] = max($cbord['border_details']['mbw']['RB'], $cbord['border_details']['B']['w']);
						$cbord['border_details']['mbw']['TL'] = max($cbord['border_details']['mbw']['TL'], $cbord['border_details']['L']['w']);
						$cbord['border_details']['mbw']['TR'] = max($cbord['border_details']['mbw']['TR'], $cbord['border_details']['R']['w']);
						$cbord['border_details']['mbw']['LT'] = max($cbord['border_details']['mbw']['LT'], $cbord['border_details']['T']['w']);
						$cbord['border_details']['mbw']['LB'] = max($cbord['border_details']['mbw']['LB'], $cbord['border_details']['B']['w']);
						if (($i + $crowsp) < $numrows && isset($cells[$i + $crowsp][$j])) { // Has Bottom adjoining cell
							if ($this->packTableData) {
								$adjc = $cells[$i + $crowsp][$j];
								$celladj = $this->_unpackCellBorder($adjc['borderbin']);
							} else {
								$celladj = & $cells[$i + $crowsp][$j];
							}
							$cbord['border_details']['mbw']['BL'] = max($cbord['border_details']['mbw']['BL'], $celladj['border_details']['L']['w'], $celladj['border_details']['mbw']['TL']);
							$cbord['border_details']['mbw']['BR'] = max($cbord['border_details']['mbw']['BR'], $celladj['border_details']['R']['w'], $celladj['border_details']['mbw']['TR']);
							$cbord['border_details']['mbw']['LB'] = max($cbord['border_details']['mbw']['LB'], $celladj['border_details']['mbw']['LT']);
							$cbord['border_details']['mbw']['RB'] = max($cbord['border_details']['mbw']['RB'], $celladj['border_details']['mbw']['RT']);
							unset($celladj);
						}
						if (($j + $ccolsp) < $numcols && isset($cells[$i][$j + $ccolsp])) { // Has Right adjoining cell
							if ($this->packTableData) {
								$adjc = $cells[$i][$j + $ccolsp];
								$celladj = $this->_unpackCellBorder($adjc['borderbin']);
							} else {
								$celladj = & $cells[$i][$j + $ccolsp];
							}
							$cbord['border_details']['mbw']['RT'] = max($cbord['border_details']['mbw']['RT'], $celladj['border_details']['T']['w'], $celladj['border_details']['mbw']['LT']);
							$cbord['border_details']['mbw']['RB'] = max($cbord['border_details']['mbw']['RB'], $celladj['border_details']['B']['w'], $celladj['border_details']['mbw']['LB']);
							$cbord['border_details']['mbw']['TR'] = max($cbord['border_details']['mbw']['TR'], $celladj['border_details']['mbw']['TL']);
							$cbord['border_details']['mbw']['BR'] = max($cbord['border_details']['mbw']['BR'], $celladj['border_details']['mbw']['BL']);
							unset($celladj);
						}

						if ($i > 0 && isset($cells[$i - 1][$j]) && (($this->packTableData && $cells[$i - 1][$j]['borderbin']) || $cells[$i - 1][$j]['border'])) { // Has Top adjoining cell
							if ($this->packTableData) {
								$adjc = $cells[$i - 1][$j];
								$celladj = $this->_unpackCellBorder($adjc['borderbin']);
							} else {
								$celladj = & $cells[$i - 1][$j];
							}
							$cbord['border_details']['mbw']['TL'] = max($cbord['border_details']['mbw']['TL'], $celladj['border_details']['L']['w'], $celladj['border_details']['mbw']['BL']);
							$cbord['border_details']['mbw']['TR'] = max($cbord['border_details']['mbw']['TR'], $celladj['border_details']['R']['w'], $celladj['border_details']['mbw']['BR']);
							$cbord['border_details']['mbw']['LT'] = max($cbord['border_details']['mbw']['LT'], $celladj['border_details']['mbw']['LB']);
							$cbord['border_details']['mbw']['RT'] = max($cbord['border_details']['mbw']['RT'], $celladj['border_details']['mbw']['RB']);

							if ($celladj['border_details']['mbw']['BL']) {
								$celladj['border_details']['mbw']['BL'] = max($cbord['border_details']['mbw']['TL'], $celladj['border_details']['mbw']['BL']);
							}
							if ($celladj['border_details']['mbw']['BR']) {
								$celladj['border_details']['mbw']['BR'] = max($celladj['border_details']['mbw']['BR'], $cbord['border_details']['mbw']['TR']);
							}
							if ($this->packTableData) {
								$cells[$i - 1][$j]['borderbin'] = $this->_packCellBorder($celladj);
							}
							unset($celladj);
						}
						if ($j > 0 && isset($cells[$i][$j - 1]) && (($this->packTableData && $cells[$i][$j - 1]['borderbin']) || $cells[$i][$j - 1]['border'])) { // Has Left adjoining cell
							if ($this->packTableData) {
								$adjc = $cells[$i][$j - 1];
								$celladj = $this->_unpackCellBorder($adjc['borderbin']);
							} else {
								$celladj = & $cells[$i][$j - 1];
							}
							$cbord['border_details']['mbw']['LT'] = max($cbord['border_details']['mbw']['LT'], $celladj['border_details']['T']['w'], $celladj['border_details']['mbw']['RT']);
							$cbord['border_details']['mbw']['LB'] = max($cbord['border_details']['mbw']['LB'], $celladj['border_details']['B']['w'], $celladj['border_details']['mbw']['RB']);
							$cbord['border_details']['mbw']['BL'] = max($cbord['border_details']['mbw']['BL'], $celladj['border_details']['mbw']['BR']);
							$cbord['border_details']['mbw']['TL'] = max($cbord['border_details']['mbw']['TL'], $celladj['border_details']['mbw']['TR']);

							if ($celladj['border_details']['mbw']['RT']) {
								$celladj['border_details']['mbw']['RT'] = max($celladj['border_details']['mbw']['RT'], $cbord['border_details']['mbw']['LT']);
							}
							if ($celladj['border_details']['mbw']['RB']) {
								$celladj['border_details']['mbw']['RB'] = max($celladj['border_details']['mbw']['RB'], $cbord['border_details']['mbw']['LB']);
							}
							if ($this->packTableData) {
								$cells[$i][$j - 1]['borderbin'] = $this->_packCellBorder($celladj);
							}
							unset($celladj);
						}


						// Update maximum cell border width at LRTB edges of table - used for overall table width
						if ($j == 0 && $cbord['border_details']['L']['w']) {
							$table['max_cell_border_width']['L'] = max($table['max_cell_border_width']['L'], $cbord['border_details']['L']['w']);
						}
						if (($j == ($numcols - 1) || ($j + $ccolsp) == $numcols ) && $cbord['border_details']['R']['w']) {
							$table['max_cell_border_width']['R'] = max($table['max_cell_border_width']['R'], $cbord['border_details']['R']['w']);
						}
						if ($i == 0 && $cbord['border_details']['T']['w']) {
							$table['max_cell_border_width']['T'] = max($table['max_cell_border_width']['T'], $cbord['border_details']['T']['w']);
						}
						if (($i == ($numrows - 1) || ($i + $crowsp) == $numrows ) && $cbord['border_details']['B']['w']) {
							$table['max_cell_border_width']['B'] = max($table['max_cell_border_width']['B'], $cbord['border_details']['B']['w']);
						}
					}
					/* -- END TABLES-ADVANCED-BORDERS -- */

					if ($this->packTableData) {
						$cell['borderbin'] = $this->_packCellBorder($cbord);
					}

					unset($cbord);

					unset($cell);
				}
			}
		}
		unset($cell);
	}

	// END FIX BORDERS ************************************************************************************

	function _reverseTableDir(&$table)
	{
		$cells = &$table['cells'];
		$numcols = $table['nc'];
		$numrows = $table['nr'];
		for ($i = 0; $i < $numrows; $i++) { // Rows
			$row = [];
			for ($j = ($numcols - 1); $j >= 0; $j--) { // Columns
				if (isset($cells[$i][$j]) && $cells[$i][$j]) {
					$cell = &$cells[$i][$j];
					$col = $numcols - $j - 1;
					if (isset($cell['colspan']) && $cell['colspan'] > 1) {
						$col -= ($cell['colspan'] - 1);
					}
					// Nested content
					if (isset($cell['textbuffer'])) {
						for ($n = 0; $n < count($cell['textbuffer']); $n++) {
							$t = $cell['textbuffer'][$n][0];
							if (substr($t, 0, 19) == "\xbb\xa4\xactype=nestedtable") {
								$objattr = $this->_getObjAttr($t);
								$objattr['col'] = $col;
								$cell['textbuffer'][$n][0] = "\xbb\xa4\xactype=nestedtable,objattr=" . serialize($objattr) . "\xbb\xa4\xac";
								$this->table[($this->tableLevel + 1)][$objattr['nestedcontent']]['nestedpos'][1] = $col;
							}
						}
					}
					$row[$col] = $cells[$i][$j];
					unset($cell);
				}
			}
			for ($f = 0; $f < $numcols; $f++) {
				if (!isset($row[$f])) {
					$row[$f] = 0;
				}
			}
			$table['cells'][$i] = $row;
		}
	}

	function _tableWrite(&$table, $split = false, $startrow = 0, $startcol = 0, $splitpg = 0, $rety = 0)
	{
		$level = $table['level'];
		$levelid = $table['levelid'];

		$cells = &$table['cells'];
		$numcols = $table['nc'];
		$numrows = $table['nr'];
		$maxbwtop = 0;
		if ($this->ColActive && $level == 1) {
			$this->breakpoints[$this->CurrCol][] = $this->y;
		} // *COLUMNS*

		if (!$split || ($startrow == 0 && $splitpg == 0) || $startrow > 0) {
			// TABLE TOP MARGIN
			if ($table['margin']['T']) {
				if (!$this->table_rotate && $level == 1) {
					$this->DivLn($table['margin']['T'], $this->blklvl, true, 1);  // collapsible
				} else {
					$this->y += ($table['margin']['T']);
				}
			}
			// Advance down page by half width of top border
			if ($table['borders_separate']) {
				if ($startrow > 0 && (!isset($table['is_thead']) || count($table['is_thead']) == 0)) {
					$adv = $table['border_spacing_V'] / 2;
				} else {
					$adv = $table['padding']['T'] + $table['border_details']['T']['w'] + $table['border_spacing_V'] / 2;
				}
			} else {
				$adv = $table['max_cell_border_width']['T'] / 2;
			}
			if (!$this->table_rotate && $level == 1) {
				$this->DivLn($adv);
			} else {
				$this->y += $adv;
			}
		}

		if ($level == 1) {
			$this->x = $this->lMargin + $this->blk[$this->blklvl]['outer_left_margin'] + $this->blk[$this->blklvl]['padding_left'] + $this->blk[$this->blklvl]['border_left']['w'];
			$x0 = $this->x;
			$y0 = $this->y;
			$right = $x0 + $this->blk[$this->blklvl]['inner_width'];
			$outerfilled = $this->y; // Keep track of how far down the outer DIV bgcolor is painted (NB rowspans)
			$this->outerfilled = $this->y;
			$this->colsums = [];
		} else {
			$x0 = $this->x;
			$y0 = $this->y;
			$right = $x0 + $table['w'];
		}

		if ($this->table_rotate) {
			$temppgwidth = $this->tbrot_maxw;
			$this->PageBreakTrigger = $pagetrigger = $y0 + ($this->blk[$this->blklvl]['inner_width']);
			if ($level == 1) {
				$this->tbrot_y0 = $this->y - $adv - $table['margin']['T'];
				$this->tbrot_x0 = $this->x;
				$this->tbrot_w = $table['w'];
				if ($table['borders_separate']) {
					$this->tbrot_h = $table['margin']['T'] + $table['padding']['T'] + $table['border_details']['T']['w'] + $table['border_spacing_V'] / 2;
				} else {
					$this->tbrot_h = $table['margin']['T'] + $table['padding']['T'] + $table['max_cell_border_width']['T'];
				}
			}
		} else {
			$this->PageBreakTrigger = $pagetrigger = ($this->h - $this->bMargin);
			if ($level == 1) {
				$temppgwidth = $this->blk[$this->blklvl]['inner_width'];
				if (isset($table['a']) and ( $table['w'] < $this->blk[$this->blklvl]['inner_width'])) {
					if ($table['a'] == 'C') {
						$x0 += ((($right - $x0) - $table['w']) / 2);
					} elseif ($table['a'] == 'R') {
						$x0 = $right - $table['w'];
					}
				}
			} else {
				$temppgwidth = $table['w'];
			}
		}
		if (!isset($table['overflow'])) {
			$table['overflow'] = null;
		}
		if ($table['overflow'] == 'hidden' && $level == 1 && !$this->table_rotate && !$this->ColActive) {
			// Bounding rectangle to clip
			$this->tableClipPath = sprintf('q %.3F %.3F %.3F %.3F re W n', $x0 * Mpdf::SCALE, $this->h * Mpdf::SCALE, $this->blk[$this->blklvl]['inner_width'] * Mpdf::SCALE, -$this->h * Mpdf::SCALE);
			$this->_out($this->tableClipPath);
		} else {
			$this->tableClipPath = '';
		}


		if ($table['borders_separate']) {
			$indent = $table['margin']['L'] + $table['border_details']['L']['w'] + $table['padding']['L'] + $table['border_spacing_H'] / 2;
		} else {
			$indent = $table['margin']['L'] + $table['max_cell_border_width']['L'] / 2;
		}
		$x0 += $indent;

		$returny = 0;
		$lastCol = 0;
		$tableheader = [];
		$tablefooter = [];
		$tableheaderrowheight = 0;
		$tablefooterrowheight = 0;
		$footery = 0;

		// mPD 3.0 Set the Page & Column where table starts
		if (($this->mirrorMargins) && (($this->page) % 2 == 0)) { // EVEN
			$tablestartpage = 'EVEN';
		} elseif (($this->mirrorMargins) && (($this->page) % 2 == 1)) { // ODD
			$tablestartpage = 'ODD';
		} else {
			$tablestartpage = '';
		}
		if ($this->ColActive) {
			$tablestartcolumn = $this->CurrCol;
		} else {
			$tablestartcolumn = '';
		}

		$y = $h = 0;
		for ($i = 0; $i < $numrows; $i++) { // Rows
			if (isset($table['is_tfoot'][$i]) && $table['is_tfoot'][$i] && $level == 1) {
				$tablefooterrowheight += $table['hr'][$i];
				$tablefooter[$i][0]['trbackground-images'] = $table['trbackground-images'][$i];
				$tablefooter[$i][0]['trgradients'] = $table['trgradients'][$i];
				$tablefooter[$i][0]['trbgcolor'] = $table['bgcolor'][$i];
				for ($j = $startcol; $j < $numcols; $j++) { // Columns
					if (isset($cells[$i][$j]) && $cells[$i][$j]) {
						$cell = &$cells[$i][$j];
						if ($split) {
							if ($table['colPg'][$j] != $splitpg) {
								continue;
							}
							list($x, $w) = $this->_splitTableGetWidth($table, $i, $j);
							$js = $j - $startcol;
						} else {
							list($x, $w) = $this->_tableGetWidth($table, $i, $j);
							$js = $j;
						}

						list($y, $h) = $this->_tableGetHeight($table, $i, $j);
						$x += $x0;
						$y += $y0;
						// Get info of tfoot ==>> table footer
						$tablefooter[$i][$js]['x'] = $x;
						$tablefooter[$i][$js]['y'] = $y;
						$tablefooter[$i][$js]['h'] = $h;
						$tablefooter[$i][$js]['w'] = $w;
						if (isset($cell['textbuffer'])) {
							$tablefooter[$i][$js]['textbuffer'] = $cell['textbuffer'];
						} else {
							$tablefooter[$i][$js]['textbuffer'] = '';
						}
						$tablefooter[$i][$js]['a'] = $cell['a'];
						$tablefooter[$i][$js]['R'] = $cell['R'];
						$tablefooter[$i][$js]['va'] = $cell['va'];
						$tablefooter[$i][$js]['mih'] = $cell['mih'];
						if (isset($cell['gradient'])) {
							$tablefooter[$i][$js]['gradient'] = $cell['gradient']; // *BACKGROUNDS*
						}
						if (isset($cell['background-image'])) {
							$tablefooter[$i][$js]['background-image'] = $cell['background-image']; // *BACKGROUNDS*
						}

						// CELL FILL BGCOLOR
						if (!$this->simpleTables) {
							if ($this->packTableData) {
								$c = $this->_unpackCellBorder($cell['borderbin']);
								$tablefooter[$i][$js]['border'] = $c['border'];
								$tablefooter[$i][$js]['border_details'] = $c['border_details'];
							} else {
								$tablefooter[$i][$js]['border'] = $cell['border'];
								$tablefooter[$i][$js]['border_details'] = $cell['border_details'];
							}
						} elseif ($this->simpleTables) {
							$tablefooter[$i][$js]['border'] = $table['simple']['border'];
							$tablefooter[$i][$js]['border_details'] = $table['simple']['border_details'];
						}
						$tablefooter[$i][$js]['bgcolor'] = $cell['bgcolor'];
						$tablefooter[$i][$js]['padding'] = $cell['padding'];
						if (isset($cell['rowspan'])) {
							$tablefooter[$i][$js]['rowspan'] = $cell['rowspan'];
						}
						if (isset($cell['colspan'])) {
							$tablefooter[$i][$js]['colspan'] = $cell['colspan'];
						}
						if (isset($cell['direction'])) {
							$tablefooter[$i][$js]['direction'] = $cell['direction'];
						}
						if (isset($cell['cellLineHeight'])) {
							$tablefooter[$i][$js]['cellLineHeight'] = $cell['cellLineHeight'];
						}
						if (isset($cell['cellLineStackingStrategy'])) {
							$tablefooter[$i][$js]['cellLineStackingStrategy'] = $cell['cellLineStackingStrategy'];
						}
						if (isset($cell['cellLineStackingShift'])) {
							$tablefooter[$i][$js]['cellLineStackingShift'] = $cell['cellLineStackingShift'];
						}
					}
				}
			}
		}

		if ($level == 1) {
			$this->_out('___TABLE___BACKGROUNDS' . $this->uniqstr);
		}
		$tableheaderadj = 0;
		$tablefooteradj = 0;

		$tablestartpageno = $this->page;

		// Draw Table Contents and Borders
		for ($i = 0; $i < $numrows; $i++) { // Rows
			if ($split && $startrow > 0) {
				$thnr = (isset($table['is_thead']) ? count($table['is_thead']) : 0);
				if ($i >= $thnr && $i < $startrow) {
					continue;
				}
				if ($i == $startrow) {
					$returny = $rety - $tableheaderrowheight;
				}
			}

			// Get Maximum row/cell height in row - including rowspan>1 + 1 overlapping
			$maxrowheight = $this->_tableGetMaxRowHeight($table, $i);

			$skippage = false;
			$newpagestarted = false;
			for ($j = $startcol; $j < $numcols; $j++) { // Columns
				if ($split) {
					if ($table['colPg'][$j] > $splitpg) {
						break;
					}
					$lastCol = $j;
				}
				if (isset($cells[$i][$j]) && $cells[$i][$j]) {
					$cell = &$cells[$i][$j];
					if ($split) {
						$lastCol = $j + (isset($cell['colspan']) ? ($cell['colspan'] - 1) : 0);
						list($x, $w) = $this->_splitTableGetWidth($table, $i, $j);
					} else {
						list($x, $w) = $this->_tableGetWidth($table, $i, $j);
					}

					list($y, $h) = $this->_tableGetHeight($table, $i, $j);
					$x += $x0;
					$y += $y0;
					$y -= $returny;

					if ($table['borders_separate']) {
						if (!empty($tablefooter) || $i == ($numrows - 1) || (isset($cell['rowspan']) && ($i + $cell['rowspan']) == $numrows) || (!isset($cell['rowspan']) && ($i + 1) == $numrows)) {
							$extra = $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
							// $extra = $table['margin']['B'] + $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V']/2;
						} else {
							$extra = $table['border_spacing_V'] / 2;
						}
					} else {
						$extra = $table['max_cell_border_width']['B'] / 2;
					}

					if ($j == $startcol && ((($y + $maxrowheight + $extra ) > ($pagetrigger + 0.001)) || (($this->keepColumns || !$this->ColActive) && !empty($tablefooter) && ($y + $maxrowheight + $tablefooterrowheight + $extra) > $pagetrigger) && ($this->tableLevel == 1 && $i < ($numrows - $table['headernrows']))) && ($y0 > 0 || $x0 > 0) && !$this->InFooter && $this->autoPageBreak) {
						if (!$skippage) {
							$finalSpread = true;
							$firstSpread = true;
							if ($split) {
								for ($t = $startcol; $t < $numcols; $t++) {
									// Are there more columns to print on a next page?
									if ($table['colPg'][$t] > $splitpg) {
										$finalSpread = false;
										break;
									}
								}
								if ($startcol > 0) {
									$firstSpread = false;
								}
							}

							if (($this->keepColumns || !$this->ColActive) && !empty($tablefooter) && $i > 0) {
								$this->y = $y;
								$ya = $this->y;
								$this->TableHeaderFooter($tablefooter, $tablestartpage, $tablestartcolumn, 'F', $level, $firstSpread, $finalSpread);
								if ($this->table_rotate) {
									$this->tbrot_h += $this->y - $ya;
								}
								$tablefooteradj = $this->y - $ya;
							}
							$y -= $y0;
							$returny += $y;

							$oldcolumn = $this->CurrCol;
							if ($this->AcceptPageBreak()) {
								$newpagestarted = true;
								$this->y = $y + $y0;

								// Move down to account for border-spacing or
								// extra half border width in case page breaks in middle
								if ($i > 0 && !$this->table_rotate && $level == 1 && !$this->ColActive) {
									if ($table['borders_separate']) {
										$adv = $table['border_spacing_V'] / 2;
										// If table footer
										if (($this->keepColumns || !$this->ColActive) && !empty($tablefooter) && $i > 0) {
											$adv += ($table['padding']['B'] + $table['border_details']['B']['w']);
										}
									} else {
										$maxbwtop = 0;
										$maxbwbottom = 0;
										if (!$this->simpleTables) {
											if (!empty($tablefooter)) {
												$maxbwbottom = $table['max_cell_border_width']['B'];
											} else {
												$brow = $i - 1;
												for ($ctj = 0; $ctj < $numcols; $ctj++) {
													if (isset($cells[$brow][$ctj]) && $cells[$brow][$ctj]) {
														if ($this->packTableData) {
															list($bt, $br, $bb, $bl) = $this->_getBorderWidths($cells[$brow][$ctj]['borderbin']);
														} else {
															$bb = $cells[$brow][$ctj]['border_details']['B']['w'];
														}
														$maxbwbottom = max($maxbwbottom, $bb);
													}
												}
											}
											if (!empty($tableheader)) {
												$maxbwtop = $table['max_cell_border_width']['T'];
											} else {
												$trow = $i - 1;
												for ($ctj = 0; $ctj < $numcols; $ctj++) {
													if (isset($cells[$trow][$ctj]) && $cells[$trow][$ctj]) {
														if ($this->packTableData) {
															list($bt, $br, $bb, $bl) = $this->_getBorderWidths($cells[$trow][$ctj]['borderbin']);
														} else {
															$bt = $cells[$trow][$ctj]['border_details']['T']['w'];
														}
														$maxbwtop = max($maxbwtop, $bt);
													}
												}
											}
										} elseif ($this->simpleTables) {
											$maxbwtop = $table['simple']['border_details']['T']['w'];
											$maxbwbottom = $table['simple']['border_details']['B']['w'];
										}
										$adv = $maxbwbottom / 2;
									}
									$this->y += $adv;
								}

								// Rotated table split over pages - needs this->y for borders/backgrounds
								if ($i > 0 && $this->table_rotate && $level == 1) {
									// 		$this->y = $y0 + $this->tbrot_w;
								}

								if ($this->tableClipPath) {
									$this->_out("Q");
								}

								$bx = $x0;
								$by = $y0;

								if ($table['borders_separate']) {
									$bx -= ($table['padding']['L'] + $table['border_details']['L']['w'] + $table['border_spacing_H'] / 2);
									if ($tablestartpageno != $this->page) { // IF already broken across a previous pagebreak
										$by += $table['max_cell_border_width']['T'] / 2;
										if (empty($tableheader)) {
											$by -= ($table['border_spacing_V'] / 2);
										}
									} else {
										$by -= ($table['padding']['T'] + $table['border_details']['T']['w'] + $table['border_spacing_V'] / 2);
									}
								} elseif ($tablestartpageno != $this->page && !empty($tableheader)) {
									$by += $maxbwtop / 2;
								}

								$by -= $tableheaderadj;
								$bh = $this->y - $by + $tablefooteradj;
								if (!$table['borders_separate']) {
									$bh -= $adv;
								}
								if ($split) {
									$bw = 0;
									for ($t = $startcol; $t < $numcols; $t++) {
										if ($table['colPg'][$t] == $splitpg) {
											$bw += $table['wc'][$t];
										}
										if ($table['colPg'][$t] > $splitpg) {
											break;
										}
									}
									if ($table['borders_separate']) {
										if ($firstSpread) {
											$bw += $table['padding']['L'] + $table['border_details']['L']['w'] + $table['border_spacing_H'];
										} else {
											$bx += ($table['padding']['L'] + $table['border_details']['L']['w']);
											$bw += $table['border_spacing_H'];
										}
										if ($finalSpread) {
											$bw += $table['padding']['R'] + $table['border_details']['R']['w'] / 2 + $table['border_spacing_H'];
										}
									}
								} else {
									$bw = $table['w'] - ($table['max_cell_border_width']['L'] / 2) - ($table['max_cell_border_width']['R'] / 2) - $table['margin']['L'] - $table['margin']['R'];
								}

								if ($this->splitTableBorderWidth && ($this->keepColumns || !$this->ColActive) && empty($tablefooter) && $i > 0 && $table['border_details']['B']['w']) {
									$prevDrawColor = $this->DrawColor;
									$lw = $this->LineWidth;
									$this->SetLineWidth($this->splitTableBorderWidth);
									$this->SetDColor($table['border_details']['B']['c']);
									$this->SetLineJoin(0);
									$this->SetLineCap(0);
									$blx = $bx;
									$blw = $bw;
									if (!$table['borders_separate']) {
										$blx -= ($table['max_cell_border_width']['L'] / 2);
										$blw += ($table['max_cell_border_width']['L'] / 2 + $table['max_cell_border_width']['R'] / 2);
									}
									$this->Line($blx, $this->y + ($this->splitTableBorderWidth / 2), $blx + $blw, $this->y + ($this->splitTableBorderWidth / 2));
									$this->DrawColor = $prevDrawColor;
									$this->_out($this->DrawColor);
									$this->SetLineWidth($lw);
									$this->SetLineJoin(2);
									$this->SetLineCap(2);
								}

								if (!$this->ColActive && ($i > 0 || $j > 0)) {
									if (isset($table['bgcolor'][-1])) {
										$color = $this->colorConverter->convert($table['bgcolor'][-1], $this->PDFAXwarnings);
										if ($color) {
											if (!$table['borders_separate']) {
												$bh -= $table['max_cell_border_width']['B'] / 2;
											}
											$this->tableBackgrounds[$level * 9][] = ['gradient' => false, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'col' => $color];
										}
									}

									/* -- BACKGROUNDS -- */
									if (isset($table['gradient'])) {
										$g = $this->gradient->parseBackgroundGradient($table['gradient']);
										if ($g) {
											$this->tableBackgrounds[$level * 9 + 1][] = ['gradient' => true, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
										}
									}

									if (isset($table['background-image'])) {
										if ($table['background-image']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $table['background-image']['gradient'])) {
											$g = $this->gradient->parseMozGradient($table['background-image']['gradient']);
											if ($g) {
												$this->tableBackgrounds[$level * 9 + 1][] = ['gradient' => true, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
											}
										} else {
											$image_id = $table['background-image']['image_id'];
											$orig_w = $table['background-image']['orig_w'];
											$orig_h = $table['background-image']['orig_h'];
											$x_pos = $table['background-image']['x_pos'];
											$y_pos = $table['background-image']['y_pos'];
											$x_repeat = $table['background-image']['x_repeat'];
											$y_repeat = $table['background-image']['y_repeat'];
											$resize = $table['background-image']['resize'];
											$opacity = $table['background-image']['opacity'];
											$itype = $table['background-image']['itype'];
											$this->tableBackgrounds[$level * 9 + 2][] = ['x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => '', 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype];
										}
									}
									/* -- END BACKGROUNDS -- */
								}

								// $this->AcceptPageBreak() has moved tablebuffer to $this->pages content
								if ($this->tableBackgrounds) {
									$s = $this->PrintTableBackgrounds();
									if ($this->bufferoutput) {
										$this->headerbuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->headerbuffer);
										$this->headerbuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', " ", $this->headerbuffer);
									} else {
										$this->pages[$this->page] = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->pages[$this->page]);
										$this->pages[$this->page] = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', " ", $this->pages[$this->page]);
									}
									$this->tableBackgrounds = [];
								}

								if ($split) {
									if ($i == 0 && $j == 0) {
										$y0 = -1;
									} elseif ($finalSpread) {
										$splitpg = 0;
										$startcol = 0;
										$startrow = $i;
									} else {
										$splitpg++;
										$startcol = $t;
										$returny -= $y;
									}
									return [false, $startrow, $startcol, $splitpg, $returny, $y0];
								}

								$this->AddPage($this->CurOrientation);

								$this->_out('___TABLE___BACKGROUNDS' . $this->uniqstr);


								if ($this->tableClipPath) {
									$this->_out($this->tableClipPath);
								}

								// Added to correct for OddEven Margins
								$x = $x + $this->MarginCorrection;
								$x0 = $x0 + $this->MarginCorrection;

								if ($this->splitTableBorderWidth && ($this->keepColumns || !$this->ColActive) && empty($tableheader) && $i > 0 && $table['border_details']['T']['w']) {
									$prevDrawColor = $this->DrawColor;
									$lw = $this->LineWidth;
									$this->SetLineWidth($this->splitTableBorderWidth);
									$this->SetDColor($table['border_details']['T']['c']);
									$this->SetLineJoin(0);
									$this->SetLineCap(0);
									$blx += $this->MarginCorrection;
									$this->Line($blx, $this->y - ($this->splitTableBorderWidth / 2), $blx + $blw, $this->y - ($this->splitTableBorderWidth / 2));
									$this->DrawColor = $prevDrawColor;
									$this->_out($this->DrawColor);
									$this->SetLineWidth($lw);
									$this->SetLineJoin(2);
									$this->SetLineCap(2);
								}

								// Move down to account for half of top border-spacing or
								// extra half border width in case page was broken in middle
								if ($i > 0 && !$this->table_rotate && $level == 1 && $table['headernrows'] == 0) {
									if ($table['borders_separate']) {
										$adv = $table['border_spacing_V'] / 2;
									} else {
										$maxbwtop = 0;
										for ($ctj = 0; $ctj < $numcols; $ctj++) {
											if (isset($cells[$i][$ctj]) && $cells[$i][$ctj]) {
												if (!$this->simpleTables) {
													if ($this->packTableData) {
														list($bt, $br, $bb, $bl) = $this->_getBorderWidths($cells[$i][$ctj]['borderbin']);
													} else {
														$bt = $cells[$i][$ctj]['border_details']['T']['w'];
													}
													$maxbwtop = max($maxbwtop, $bt);
												} elseif ($this->simpleTables) {
													$maxbwtop = max($maxbwtop, $table['simple']['border_details']['T']['w']);
												}
											}
										}
										$adv = $maxbwtop / 2;
									}
									$this->y += $adv;
								}


								if ($this->table_rotate) {
									$this->tbrot_x0 = $this->lMargin + $this->blk[$this->blklvl]['outer_left_margin'] + $this->blk[$this->blklvl]['padding_left'] + $this->blk[$this->blklvl]['border_left']['w'];
									if ($table['borders_separate']) {
										$this->tbrot_h = $table['margin']['T'] + $table['padding']['T'] + $table['border_details']['T']['w'] + $table['border_spacing_V'] / 2;
									} else {
										$this->tbrot_h = $table['margin']['T'] + $table['max_cell_border_width']['T'];
									}
									$this->tbrot_y0 = $this->y;
									$pagetrigger = $y0 - $tableheaderadj + ($this->blk[$this->blklvl]['inner_width']);
								} else {
									$pagetrigger = $this->PageBreakTrigger;
								}

								if ($this->kwt_saved && $level == 1) {
									$this->kwt_moved = true;
								}


								if (!empty($tableheader)) {
									$ya = $this->y;
									$this->TableHeaderFooter($tableheader, $tablestartpage, $tablestartcolumn, 'H', $level);
									if ($this->table_rotate) {
										$this->tbrot_h = $this->y - $ya;
									}
									$tableheaderadj = $this->y - $ya;
								} elseif ($i == 0 && !$this->table_rotate && $level == 1 && !$this->ColActive) {
									// Advance down page
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

								$outerfilled = 0;
								$y = $y0 = $this->y;
							}

							/* -- COLUMNS -- */
							// COLS
							// COLUMN CHANGE
							if ($this->CurrCol != $oldcolumn) {
								// Added to correct for Columns
								$x += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
								$x0 += $this->ChangeColumn * ($this->ColWidth + $this->ColGap);
								if ($this->CurrCol == 0) {  // just added a page - possibly with tableheader
									$y0 = $this->y;  // this->y0 is global used by Columns - $y0 is internal to tablewrite
								} else {
									$y0 = $this->y0;  // this->y0 is global used by Columns - $y0 is internal to tablewrite
								}
								$y = $y0;
								$outerfilled = 0;
								if ($this->CurrCol != 0 && ($this->keepColumns && $this->ColActive) && !empty($tableheader) && $i > 0) {
									$this->x = $x;
									$this->y = $y;
									$this->TableHeaderFooter($tableheader, $tablestartpage, $tablestartcolumn, 'H', $level);
									$y0 = $y = $this->y;
								}
							}
							/* -- END COLUMNS -- */
						}
						$skippage = true;
					}

					$this->x = $x;
					$this->y = $y;

					if ($this->kwt_saved && $level == 1) {
						$this->printkwtbuffer();
						$x0 = $x = $this->x;
						$y0 = $y = $this->y;
						$this->kwt_moved = false;
						$this->kwt_saved = false;
					}


					// Set the Page & Column where table actually starts
					if ($i == 0 && $j == 0 && $level == 1) {
						if (($this->mirrorMargins) && (($this->page) % 2 == 0)) {    // EVEN
							$tablestartpage = 'EVEN';
						} elseif (($this->mirrorMargins) && (($this->page) % 2 == 1)) {    // ODD
							$tablestartpage = 'ODD';
						} else {
							$tablestartpage = '';
						}
						$tablestartpageno = $this->page;
						if ($this->ColActive) {
							$tablestartcolumn = $this->CurrCol;
						} // *COLUMNS*
					}

					// ALIGN
					$align = $cell['a'];

					/* -- COLUMNS -- */
					// If outside columns, this is done in PaintDivBB
					if ($this->ColActive) {
						// OUTER FILL BGCOLOR of DIVS
						if ($this->blklvl > 0 && ($j == 0) && !$this->table_rotate && $level == 1) {
							$firstblockfill = $this->GetFirstBlockFill();
							if ($firstblockfill && $this->blklvl >= $firstblockfill) {
								$divh = $maxrowheight;
								// Last row
								if ((!isset($cell['rowspan']) && $i == $numrows - 1) || (isset($cell['rowspan']) && (($i == $numrows - 1 && $cell['rowspan'] < 2) || ($cell['rowspan'] > 1 && ($i + $cell['rowspan'] - 1) == $numrows - 1)))) {
									if ($table['borders_separate']) {
										$adv = $table['margin']['B'] + $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
									} else {
										$adv = $table['margin']['B'] + $table['max_cell_border_width']['B'] / 2;
									}
									$divh += $adv;  // last row: fill bottom half of bottom border (y advanced at end)
								}

								if (($this->y + $divh) > $outerfilled) { // if not already painted by previous rowspan
									$bak_x = $this->x;
									$bak_y = $this->y;
									if ($outerfilled > $this->y) {
										$divh = ($this->y + $divh) - $outerfilled;
										$this->y = $outerfilled;
									}

									$this->DivLn($divh, -3, false);
									$outerfilled = $this->y + $divh;
									// Reset current block fill
									$bcor = $this->blk[$this->blklvl]['bgcolorarray'];
									if ($bcor) {
										$this->SetFColor($bcor);
									}
									$this->x = $bak_x;
									$this->y = $bak_y;
								}
							}
						}
					}

					// TABLE BACKGROUND FILL BGCOLOR - for cellSpacing
					if ($this->ColActive) {
						if ($table['borders_separate']) {
							$fill = isset($table['bgcolor'][-1]) ? $table['bgcolor'][-1] : 0;
							if ($fill) {
								$color = $this->colorConverter->convert($fill, $this->PDFAXwarnings);
								if ($color) {
									$xadj = ($table['border_spacing_H'] / 2);
									$yadj = ($table['border_spacing_V'] / 2);
									$wadj = $table['border_spacing_H'];
									$hadj = $table['border_spacing_V'];
									if ($i == 0) {  // Top
										$yadj += $table['padding']['T'] + $table['border_details']['T']['w'];
										$hadj += $table['padding']['T'] + $table['border_details']['T']['w'];
									}
									if ($j == 0) {  // Left
										$xadj += $table['padding']['L'] + $table['border_details']['L']['w'];
										$wadj += $table['padding']['L'] + $table['border_details']['L']['w'];
									}
									if ($i == ($numrows - 1) || (isset($cell['rowspan']) && ($i + $cell['rowspan']) == $numrows) || (!isset($cell['rowspan']) && ($i + 1) == $numrows)) { // Bottom
										$hadj += $table['padding']['B'] + $table['border_details']['B']['w'];
									}
									if ($j == ($numcols - 1) || (isset($cell['colspan']) && ($j + $cell['colspan']) == $numcols) || (!isset($cell['colspan']) && ($j + 1) == $numcols)) { // Right
										$wadj += $table['padding']['R'] + $table['border_details']['R']['w'];
									}
									$this->SetFColor($color);
									$this->Rect($x - $xadj, $y - $yadj, $w + $wadj, $h + $hadj, 'F');
								}
							}
						}
					}
					/* -- END COLUMNS -- */

					if ($table['empty_cells'] != 'hide' || !empty($cell['textbuffer']) || (isset($cell['nestedcontent']) && $cell['nestedcontent']) || !$table['borders_separate']) {
						$paintcell = true;
					} else {
						$paintcell = false;
					}

					// Set Borders
					$bord = 0;
					$bord_det = [];

					if (!$this->simpleTables) {
						if ($this->packTableData) {
							$c = $this->_unpackCellBorder($cell['borderbin']);
							$bord = $c['border'];
							$bord_det = $c['border_details'];
						} else {
							$bord = $cell['border'];
							$bord_det = $cell['border_details'];
						}
					} elseif ($this->simpleTables) {
						$bord = $table['simple']['border'];
						$bord_det = $table['simple']['border_details'];
					}

					// TABLE ROW OR CELL FILL BGCOLOR
					$fill = 0;
					if (isset($cell['bgcolor']) && $cell['bgcolor'] && $cell['bgcolor'] != 'transparent') {
						$fill = $cell['bgcolor'];
						$leveladj = 6;
					} elseif (isset($table['bgcolor'][$i]) && $table['bgcolor'][$i] && $table['bgcolor'][$i] != 'transparent') { // Row color
						$fill = $table['bgcolor'][$i];
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
					if (isset($cell['gradient']) && $cell['gradient'] && $paintcell) {
						$g = $this->gradient->parseBackgroundGradient($cell['gradient']);
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

					if (isset($cell['background-image']) && $paintcell) {
						if (isset($cell['background-image']['gradient']) && $cell['background-image']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $cell['background-image']['gradient'])) {
							$g = $this->gradient->parseMozGradient($cell['background-image']['gradient']);
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
						} elseif (isset($cell['background-image']['image_id']) && $cell['background-image']['image_id']) { // Background pattern
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
								list($orig_w, $orig_h, $x_repeat, $y_repeat) = $this->_resizeBackgroundImage($cell['background-image']['orig_w'], $cell['background-image']['orig_h'], $pw, $ph, $cell['background-image']['resize'], $cell['background-image']['x_repeat'], $cell['background-image']['y_repeat']);
								$this->patterns[$n] = ['x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'pgh' => $this->h, 'image_id' => $cell['background-image']['image_id'], 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $cell['background-image']['x_pos'], 'y_pos' => $cell['background-image']['y_pos'], 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat];
								if ($cell['background-image']['opacity'] > 0 && $cell['background-image']['opacity'] < 1) {
									$opac = $this->SetAlpha($cell['background-image']['opacity'], 'Normal', true);
								} else {
									$opac = '';
								}
								$this->_out(sprintf('q /Pattern cs /P%d scn %s %.3F %.3F %.3F %.3F re f Q', $n, $opac, $px * Mpdf::SCALE, ($this->h - $py) * Mpdf::SCALE, $pw * Mpdf::SCALE, -$ph * Mpdf::SCALE));
							} else {
								$image_id = $cell['background-image']['image_id'];
								$orig_w = $cell['background-image']['orig_w'];
								$orig_h = $cell['background-image']['orig_h'];
								$x_pos = $cell['background-image']['x_pos'];
								$y_pos = $cell['background-image']['y_pos'];
								$x_repeat = $cell['background-image']['x_repeat'];
								$y_repeat = $cell['background-image']['y_repeat'];
								$resize = $cell['background-image']['resize'];
								$opacity = $cell['background-image']['opacity'];
								$itype = $cell['background-image']['itype'];
								$this->tableBackgrounds[$level * 9 + 8][] = ['x' => $px, 'y' => $py, 'w' => $pw, 'h' => $ph, 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => '', 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype];
							}
						}
					}
					/* -- END BACKGROUNDS -- */

					if (isset($cell['colspan']) && $cell['colspan'] > 1) {
						$ccolsp = $cell['colspan'];
					} else {
						$ccolsp = 1;
					}
					if (isset($cell['rowspan']) && $cell['rowspan'] > 1) {
						$crowsp = $cell['rowspan'];
					} else {
						$crowsp = 1;
					}


					// but still need to do this for repeated headers...
					if (!$table['borders_separate'] && $this->tabletheadjustfinished && !$this->simpleTables) {
						if (isset($table['topntail']) && $table['topntail']) {
							$bord_det['T'] = $this->border_details($table['topntail']);
							$bord_det['T']['w'] /= $this->shrin_k;
							$this->setBorder($bord, Border::TOP);
						}
						if (isset($table['thead-underline']) && $table['thead-underline']) {
							$bord_det['T'] = $this->border_details($table['thead-underline']);
							$bord_det['T']['w'] /= $this->shrin_k;
							$this->setBorder($bord, Border::TOP);
						}
					}


					// Get info of first row ==>> table header
					// Use > 1 row if THEAD
					if (isset($table['is_thead'][$i]) && $table['is_thead'][$i] && $level == 1) {
						if ($j == 0) {
							$tableheaderrowheight += $table['hr'][$i];
						}
						$tableheader[$i][0]['trbackground-images'] = (isset($table['trbackground-images'][$i]) ? $table['trbackground-images'][$i] : null);
						$tableheader[$i][0]['trgradients'] = (isset($table['trgradients'][$i]) ? $table['trgradients'][$i] : null);
						$tableheader[$i][0]['trbgcolor'] = (isset($table['bgcolor'][$i]) ? $table['bgcolor'][$i] : null);
						$tableheader[$i][$j]['x'] = $x;
						$tableheader[$i][$j]['y'] = $y;
						$tableheader[$i][$j]['h'] = $h;
						$tableheader[$i][$j]['w'] = $w;
						if (isset($cell['textbuffer'])) {
							$tableheader[$i][$j]['textbuffer'] = $cell['textbuffer'];
						} else {
							$tableheader[$i][$j]['textbuffer'] = '';
						}
						$tableheader[$i][$j]['a'] = $cell['a'];
						$tableheader[$i][$j]['R'] = $cell['R'];

						$tableheader[$i][$j]['va'] = $cell['va'];
						$tableheader[$i][$j]['mih'] = $cell['mih'];
						$tableheader[$i][$j]['gradient'] = (isset($cell['gradient']) ? $cell['gradient'] : null); // *BACKGROUNDS*
						$tableheader[$i][$j]['background-image'] = (isset($cell['background-image']) ? $cell['background-image'] : null); // *BACKGROUNDS*
						$tableheader[$i][$j]['rowspan'] = (isset($cell['rowspan']) ? $cell['rowspan'] : null);
						$tableheader[$i][$j]['colspan'] = (isset($cell['colspan']) ? $cell['colspan'] : null);
						$tableheader[$i][$j]['bgcolor'] = $cell['bgcolor'];

						if (!$this->simpleTables) {
							$tableheader[$i][$j]['border'] = $bord;
							$tableheader[$i][$j]['border_details'] = $bord_det;
						} elseif ($this->simpleTables) {
							$tableheader[$i][$j]['border'] = $table['simple']['border'];
							$tableheader[$i][$j]['border_details'] = $table['simple']['border_details'];
						}
						$tableheader[$i][$j]['padding'] = $cell['padding'];
						if (isset($cell['direction'])) {
							$tableheader[$i][$j]['direction'] = $cell['direction'];
						}
						if (isset($cell['cellLineHeight'])) {
							$tableheader[$i][$j]['cellLineHeight'] = $cell['cellLineHeight'];
						}
						if (isset($cell['cellLineStackingStrategy'])) {
							$tableheader[$i][$j]['cellLineStackingStrategy'] = $cell['cellLineStackingStrategy'];
						}
						if (isset($cell['cellLineStackingShift'])) {
							$tableheader[$i][$j]['cellLineStackingShift'] = $cell['cellLineStackingShift'];
						}
					}

					// CELL BORDER
					if ($bord) {
						if ($table['borders_separate'] && $paintcell) {
							$this->_tableRect($x + ($table['border_spacing_H'] / 2) + ($bord_det['L']['w'] / 2), $y + ($table['border_spacing_V'] / 2) + ($bord_det['T']['w'] / 2), $w - $table['border_spacing_H'] - ($bord_det['L']['w'] / 2) - ($bord_det['R']['w'] / 2), $h - $table['border_spacing_V'] - ($bord_det['T']['w'] / 2) - ($bord_det['B']['w'] / 2), $bord, $bord_det, false, $table['borders_separate']);
						} elseif (!$table['borders_separate']) {
							$this->_tableRect($x, $y, $w, $h, $bord, $bord_det, true, $table['borders_separate']);  // true causes buffer
						}
					}

					// VERTICAL ALIGN
					if ($cell['R'] && intval($cell['R']) > 0 && intval($cell['R']) < 90 && isset($cell['va']) && $cell['va'] != 'B') {
						$cell['va'] = 'B';
					}
					if (!isset($cell['va']) || $cell['va'] == 'M') {
						$this->y += ($h - $cell['mih']) / 2;
					} elseif (isset($cell['va']) && $cell['va'] == 'B') {
						$this->y += $h - $cell['mih'];
					}

					// NESTED CONTENT
					// TEXT (and nested tables)

					$this->divwidth = $w;
					if (!empty($cell['textbuffer'])) {
						$this->cellTextAlign = $align;
						$this->cellLineHeight = $cell['cellLineHeight'];
						$this->cellLineStackingStrategy = $cell['cellLineStackingStrategy'];
						$this->cellLineStackingShift = $cell['cellLineStackingShift'];
						if ($level == 1) {
							if (isset($table['is_tfoot'][$i]) && $table['is_tfoot'][$i]) {
								if (preg_match('/{colsum([0-9]*)[_]*}/', $cell['textbuffer'][0][0], $m)) {
									$rep = sprintf("%01." . intval($m[1]) . "f", $this->colsums[$j]);
									$cell['textbuffer'][0][0] = preg_replace('/{colsum[0-9_]*}/', $rep, $cell['textbuffer'][0][0]);
								}
							} elseif (!isset($table['is_thead'][$i])) {
								if (isset($this->colsums[$j])) {
									$this->colsums[$j] += $this->toFloat($cell['textbuffer'][0][0]);
								} else {
									$this->colsums[$j] = $this->toFloat($cell['textbuffer'][0][0]);
								}
							}
						}
						$opy = $this->y;
						// mPDF ITERATION
						if ($this->iterationCounter) {
							foreach ($cell['textbuffer'] as $k => $t) {
								if (preg_match('/{iteration ([a-zA-Z0-9_]+)}/', $t[0], $m)) {
									$vname = '__' . $m[1] . '_';
									if (!isset($this->$vname)) {
										$this->$vname = 1;
									} else {
										$this->$vname++;
									}
									$cell['textbuffer'][$k][0] = preg_replace('/{iteration ' . $m[1] . '}/', $this->$vname, $cell['textbuffer'][$k][0]);
								}
							}
						}


						if ($cell['R']) {
							$cellPtSize = $cell['textbuffer'][0][11] / $this->shrin_k;
							if (!$cellPtSize) {
								$cellPtSize = $this->default_font_size;
							}
							$cellFontHeight = ($cellPtSize / Mpdf::SCALE);
							$opx = $this->x;
							$angle = intval($cell['R']);
							// Only allow 45 to 89 degrees (when bottom-aligned) or exactly 90 or -90
							if ($angle > 90) {
								$angle = 90;
							} elseif ($angle > 0 && $angle < 45) {
								$angle = 45;
							} elseif ($angle < 0) {
								$angle = -90;
							}
							$offset = ((sin(deg2rad($angle))) * 0.37 * $cellFontHeight);
							if (isset($cell['a']) && $cell['a'] == 'R') {
								$this->x += ($w) + ($offset) - ($cellFontHeight / 3) - ($cell['padding']['R'] + ($table['border_spacing_H'] / 2));
							} elseif (!isset($cell['a']) || $cell['a'] == 'C') {
								$this->x += ($w / 2) + ($offset);
							} else {
								$this->x += ($offset) + ($cellFontHeight / 3) + ($cell['padding']['L'] + ($table['border_spacing_H'] / 2));
							}
							$str = '';
							foreach ($cell['textbuffer'] as $t) {
								$str .= $t[0] . ' ';
							}
							$str = rtrim($str);
							if (!isset($cell['va']) || $cell['va'] == 'M') {
								$this->y -= ($h - $cell['mih']) / 2; // Undo what was added earlier VERTICAL ALIGN
								if ($angle > 0) {
									$this->y += (($h - $cell['mih']) / 2) + $cell['padding']['T'] + ($cell['mih'] - ($cell['padding']['T'] + $cell['padding']['B']));
								} elseif ($angle < 0) {
									$this->y += (($h - $cell['mih']) / 2) + ($cell['padding']['T'] + ($table['border_spacing_V'] / 2));
								}
							} elseif (isset($cell['va']) && $cell['va'] == 'B') {
								$this->y -= $h - $cell['mih']; // Undo what was added earlier VERTICAL ALIGN
								if ($angle > 0) {
									$this->y += $h - ($cell['padding']['B'] + ($table['border_spacing_V'] / 2));
								} elseif ($angle < 0) {
									$this->y += $h - $cell['mih'] + ($cell['padding']['T'] + ($table['border_spacing_V'] / 2));
								}
							} elseif (isset($cell['va']) && $cell['va'] == 'T') {
								if ($angle > 0) {
									$this->y += $cell['mih'] - ($cell['padding']['B'] + ($table['border_spacing_V'] / 2));
								} elseif ($angle < 0) {
									$this->y += ($cell['padding']['T'] + ($table['border_spacing_V'] / 2));
								}
							}
							$this->Rotate($angle, $this->x, $this->y);
							$s_fs = $this->FontSizePt;
							$s_f = $this->FontFamily;
							$s_st = $this->FontStyle;
							if (!empty($cell['textbuffer'][0][3])) { // Font Color
								$cor = $cell['textbuffer'][0][3];
								$this->SetTColor($cor);
							}
							$this->SetFont($cell['textbuffer'][0][4], $cell['textbuffer'][0][2], $cellPtSize, true, true);

							$this->magic_reverse_dir($str, $this->directionality, $cell['textbuffer'][0][18]);
							$this->Text($this->x, $this->y, $str, $cell['textbuffer'][0][18], $cell['textbuffer'][0][8]); // textvar
							$this->Rotate(0);
							$this->SetFont($s_f, $s_st, $s_fs, true, true);
							$this->SetTColor(0);
							$this->x = $opx;
						} else {
							if (!$this->simpleTables) {
								if ($bord_det) {
									$btlw = $bord_det['L']['w'];
									$btrw = $bord_det['R']['w'];
									$bttw = $bord_det['T']['w'];
								} else {
									$btlw = 0;
									$btrw = 0;
									$bttw = 0;
								}
								if ($table['borders_separate']) {
									$xadj = $btlw + $cell['padding']['L'] + ($table['border_spacing_H'] / 2);
									$wadj = $btlw + $btrw + $cell['padding']['L'] + $cell['padding']['R'] + $table['border_spacing_H'];
									$yadj = $bttw + $cell['padding']['T'] + ($table['border_spacing_H'] / 2);
								} else {
									$xadj = $btlw / 2 + $cell['padding']['L'];
									$wadj = ($btlw + $btrw) / 2 + $cell['padding']['L'] + $cell['padding']['R'];
									$yadj = $bttw / 2 + $cell['padding']['T'];
								}
							} elseif ($this->simpleTables) {
								if ($table['borders_separate']) { // NB twice border width
									$xadj = $table['simple']['border_details']['L']['w'] + $cell['padding']['L'] + ($table['border_spacing_H'] / 2);
									$wadj = $table['simple']['border_details']['L']['w'] + $table['simple']['border_details']['R']['w'] + $cell['padding']['L'] + $cell['padding']['R'] + $table['border_spacing_H'];
									$yadj = $table['simple']['border_details']['T']['w'] + $cell['padding']['T'] + ($table['border_spacing_H'] / 2);
								} else {
									$xadj = $table['simple']['border_details']['L']['w'] / 2 + $cell['padding']['L'];
									$wadj = ($table['simple']['border_details']['L']['w'] + $table['simple']['border_details']['R']['w']) / 2 + $cell['padding']['L'] + $cell['padding']['R'];
									$yadj = $table['simple']['border_details']['T']['w'] / 2 + $cell['padding']['T'];
								}
							}
							$this->decimal_offset = 0;
							if (substr($cell['a'], 0, 1) == 'D') {
								if (isset($cell['colspan']) && $cell['colspan'] > 1) {
									$this->cellTextAlign = $c['a'] = substr($cell['a'], 2, 1);
								} else {
									$smax = $table['decimal_align'][$j]['maxs0'];
									$d_content = $table['decimal_align'][$j]['maxs0'] + $table['decimal_align'][$j]['maxs1'];
									$this->decimal_offset = $smax;
									$extra = ($w - $d_content - $wadj);
									if ($extra > 0) {
										if (substr($cell['a'], 2, 1) == 'R') {
											$this->decimal_offset += $extra;
										} elseif (substr($cell['a'], 2, 1) == 'C') {
											$this->decimal_offset += ($extra) / 2;
										}
									}
								}
							}
							$this->divwidth = $w - $wadj;
							if ($this->divwidth == 0) {
								$this->divwidth = 0.0001;
							}
							$this->x += $xadj;
							$this->y += $yadj;
							$this->printbuffer($cell['textbuffer'], '', true, false, $cell['direction']);
						}
						$this->y = $opy;
					}

					/* -- BACKGROUNDS -- */
					if (!$this->ColActive) {
						if (isset($table['trgradients'][$i]) && ($j == 0 || $table['borders_separate'])) {
							$g = $this->gradient->parseBackgroundGradient($table['trgradients'][$i]);
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
						if (isset($table['trbackground-images'][$i]) && ($j == 0 || $table['borders_separate'])) {
							if (isset($table['trbackground-images'][$i]['gradient']) && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $table['trbackground-images'][$i]['gradient'])) {
								$g = $this->gradient->parseMozGradient($table['trbackground-images'][$i]['gradient']);
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
								$image_id = $table['trbackground-images'][$i]['image_id'];
								$orig_w = $table['trbackground-images'][$i]['orig_w'];
								$orig_h = $table['trbackground-images'][$i]['orig_h'];
								$x_pos = $table['trbackground-images'][$i]['x_pos'];
								$y_pos = $table['trbackground-images'][$i]['y_pos'];
								$x_repeat = $table['trbackground-images'][$i]['x_repeat'];
								$y_repeat = $table['trbackground-images'][$i]['y_repeat'];
								$resize = $table['trbackground-images'][$i]['resize'];
								$opacity = $table['trbackground-images'][$i]['opacity'];
								$itype = $table['trbackground-images'][$i]['itype'];
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

					// TABLE BORDER - if separate
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
						if ($i == 0) {  // Top
							$tby -= $halfspaceT + ($table['border_details']['T']['w'] / 2);
							$tbh += $halfspaceT + ($table['border_details']['T']['w'] / 2);
							$this->setBorder($tab_bord, Border::TOP);
							$corner .= 'T';
						}
						if ($i == ($numrows - 1) || (isset($cell['rowspan']) && ($i + $cell['rowspan']) == $numrows)) { // Bottom
							$tbh += $halfspaceB + ($table['border_details']['B']['w'] / 2);
							$this->setBorder($tab_bord, Border::BOTTOM);
							$corner .= 'B';
						}
						if ($j == 0) {  // Left
							$tbx -= $halfspaceL + ($table['border_details']['L']['w'] / 2);
							$tbw += $halfspaceL + ($table['border_details']['L']['w'] / 2);
							$this->setBorder($tab_bord, Border::LEFT);
							$corner .= 'L';
						}
						if ($j == ($numcols - 1) || (isset($cell['colspan']) && ($j + $cell['colspan']) == $numcols)) { // Right
							$tbw += $halfspaceR + ($table['border_details']['R']['w'] / 2);
							$this->setBorder($tab_bord, Border::RIGHT);
							$corner .= 'R';
						}
						$this->_tableRect($tbx, $tby, $tbw, $tbh, $tab_bord, $table['border_details'], false, $table['borders_separate'], 'table', $corner, $table['border_spacing_V'], $table['border_spacing_H']);
					}

					unset($cell);
					// Reset values
					$this->Reset();
				}//end of (if isset(cells)...)
			}// end of columns

			$newpagestarted = false;
			$this->tabletheadjustfinished = false;

			/* -- COLUMNS -- */
			if ($this->ColActive) {
				if (!$this->table_keep_together && $i < $numrows - 1 && $level == 1) {
					$this->breakpoints[$this->CurrCol][] = $y + $h;
				} // mPDF 6
				if (count($this->cellBorderBuffer)) {
					$this->printcellbuffer();
				}
			}
			/* -- END COLUMNS -- */

			if ($i == $numrows - 1) {
				$this->y = $y + $h;
			} // last row jump (update this->y position)
			if ($this->table_rotate && $level == 1) {
				$this->tbrot_h += $h;
			}
		} // end of rows

		if (count($this->cellBorderBuffer)) {
			$this->printcellbuffer();
		}


		if ($this->tableClipPath) {
			$this->_out("Q");
		}
		$this->tableClipPath = '';

		// Advance down page by half width of bottom border
		if ($table['borders_separate']) {
			$this->y += $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
		} else {
			$this->y += $table['max_cell_border_width']['B'] / 2;
		}

		if ($table['borders_separate'] && $level == 1) {
			$this->tbrot_h += $table['margin']['B'] + $table['padding']['B'] + $table['border_details']['B']['w'] + $table['border_spacing_V'] / 2;
		} elseif ($level == 1) {
			$this->tbrot_h += $table['margin']['B'] + $table['max_cell_border_width']['B'] / 2;
		}

		$bx = $x0;
		$by = $y0;
		if ($table['borders_separate']) {
			$bx -= ($table['padding']['L'] + $table['border_details']['L']['w'] + $table['border_spacing_H'] / 2);
			if ($tablestartpageno != $this->page) { // IF broken across page
				$by += $table['max_cell_border_width']['T'] / 2;
				if (empty($tableheader)) {
					$by -= ($table['border_spacing_V'] / 2);
				}
			} elseif ($split && $startrow > 0 && empty($tableheader)) {
				$by -= ($table['border_spacing_V'] / 2);
			} else {
				$by -= ($table['padding']['T'] + $table['border_details']['T']['w'] + $table['border_spacing_V'] / 2);
			}
		} elseif ($tablestartpageno != $this->page && !empty($tableheader)) {
			$by += $maxbwtop / 2;
		}
		$by -= $tableheaderadj;
		$bh = $this->y - $by;
		if (!$table['borders_separate']) {
			$bh -= $table['max_cell_border_width']['B'] / 2;
		}

		if ($split) {
			$bw = 0;
			$finalSpread = true;
			for ($t = $startcol; $t < $numcols; $t++) {
				if ($table['colPg'][$t] == $splitpg) {
					$bw += $table['wc'][$t];
				}
				if ($table['colPg'][$t] > $splitpg) {
					$finalSpread = false;
					break;
				}
			}
			if ($startcol == 0) {
				$firstSpread = true;
			} else {
				$firstSpread = false;
			}
			if ($table['borders_separate']) {
				$bw += $table['border_spacing_H'];
				if ($firstSpread) {
					$bw += $table['padding']['L'] + $table['border_details']['L']['w'];
				} else {
					$bx += ($table['padding']['L'] + $table['border_details']['L']['w']);
				}
				if ($finalSpread) {
					$bw += $table['padding']['R'] + $table['border_details']['R']['w'];
				}
			}
		} else {
			$bw = $table['w'] - ($table['max_cell_border_width']['L'] / 2) - ($table['max_cell_border_width']['R'] / 2) - $table['margin']['L'] - $table['margin']['R'];
		}

		if (!$this->ColActive) {
			if (isset($table['bgcolor'][-1])) {
				$color = $this->colorConverter->convert($table['bgcolor'][-1], $this->PDFAXwarnings);
				if ($color) {
					$this->tableBackgrounds[$level * 9][] = ['gradient' => false, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'col' => $color];
				}
			}

			/* -- BACKGROUNDS -- */
			if (isset($table['gradient'])) {
				$g = $this->gradient->parseBackgroundGradient($table['gradient']);
				if ($g) {
					$this->tableBackgrounds[$level * 9 + 1][] = ['gradient' => true, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
				}
			}

			if (isset($table['background-image'])) {
				if (isset($table['background-image']['gradient']) && $table['background-image']['gradient'] && preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient/', $table['background-image']['gradient'])) {
					$g = $this->gradient->parseMozGradient($table['background-image']['gradient']);
					if ($g) {
						$this->tableBackgrounds[$level * 9 + 1][] = ['gradient' => true, 'x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'gradtype' => $g['type'], 'stops' => $g['stops'], 'colorspace' => $g['colorspace'], 'coords' => $g['coords'], 'extend' => $g['extend'], 'clippath' => ''];
					}
				} else {
					$image_id = $table['background-image']['image_id'];
					$orig_w = $table['background-image']['orig_w'];
					$orig_h = $table['background-image']['orig_h'];
					$x_pos = $table['background-image']['x_pos'];
					$y_pos = $table['background-image']['y_pos'];
					$x_repeat = $table['background-image']['x_repeat'];
					$y_repeat = $table['background-image']['y_repeat'];
					$resize = $table['background-image']['resize'];
					$opacity = $table['background-image']['opacity'];
					$itype = $table['background-image']['itype'];
					$this->tableBackgrounds[$level * 9 + 2][] = ['x' => $bx, 'y' => $by, 'w' => $bw, 'h' => $bh, 'image_id' => $image_id, 'orig_w' => $orig_w, 'orig_h' => $orig_h, 'x_pos' => $x_pos, 'y_pos' => $y_pos, 'x_repeat' => $x_repeat, 'y_repeat' => $y_repeat, 'clippath' => '', 'resize' => $resize, 'opacity' => $opacity, 'itype' => $itype];
				}
			}
			/* -- END BACKGROUNDS -- */
		}

		if ($this->tableBackgrounds && $level == 1) {
			$s = $this->PrintTableBackgrounds();
			if ($this->table_rotate && !$this->processingHeader && !$this->processingFooter) {
				$this->tablebuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->tablebuffer);
				if ($level == 1) {
					$this->tablebuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', " ", $this->tablebuffer);
				}
			} elseif ($this->bufferoutput) {
				$this->headerbuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->headerbuffer);
				if ($level == 1) {
					$this->headerbuffer = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', " ", $this->headerbuffer);
				}
			} else {
				$this->pages[$this->page] = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', '\\1' . "\n" . $s . "\n", $this->pages[$this->page]);
				if ($level == 1) {
					$this->pages[$this->page] = preg_replace('/(___TABLE___BACKGROUNDS' . $this->uniqstr . ')/', " ", $this->pages[$this->page]);
				}
			}
			$this->tableBackgrounds = [];
		}


		// TABLE BOTTOM MARGIN
		if ($table['margin']['B']) {
			if (!$this->table_rotate && $level == 1) {
				$this->DivLn($table['margin']['B'], $this->blklvl, true);  // collapsible
			} else {
				$this->y += ($table['margin']['B']);
			}
		}

		if ($this->ColActive && $level == 1) {
			$this->breakpoints[$this->CurrCol][] = $this->y;
		} // *COLUMNS*

		if ($split) {
			// Are there more columns to print on a next page?
			if ($lastCol < $numcols - 1) {
				$splitpg++;
				$startcol = $lastCol + 1;
				return [false, $startrow, $startcol, $splitpg, $returny, $y0];
			} else {
				return [true, 0, 0, 0, false, false];
			}
		}
	}

	// END OF FUNCTION _tableWrite()
	/////////////////////////END OF TABLE CODE//////////////////////////////////
	/* -- END TABLES -- */

	function _putextgstates()
	{
		for ($i = 1; $i <= count($this->extgstates); $i++) {
			$this->_newobj();
			$this->extgstates[$i]['n'] = $this->n;
			$this->_out('<</Type /ExtGState');
			foreach ($this->extgstates[$i]['parms'] as $k => $v) {
				$this->_out('/' . $k . ' ' . $v);
			}
			$this->_out('>>');
			$this->_out('endobj');
		}
	}

	function _putocg()
	{
		if ($this->hasOC) {
			$this->_newobj();
			$this->n_ocg_print = $this->n;
			$this->_out('<</Type /OCG /Name ' . $this->_textstring('Print only'));
			$this->_out('/Usage <</Print <</PrintState /ON>> /View <</ViewState /OFF>>>>>>');
			$this->_out('endobj');
			$this->_newobj();
			$this->n_ocg_view = $this->n;
			$this->_out('<</Type /OCG /Name ' . $this->_textstring('Screen only'));
			$this->_out('/Usage <</Print <</PrintState /OFF>> /View <</ViewState /ON>>>>>>');
			$this->_out('endobj');
			$this->_newobj();
			$this->n_ocg_hidden = $this->n;
			$this->_out('<</Type /OCG /Name ' . $this->_textstring('Hidden'));
			$this->_out('/Usage <</Print <</PrintState /OFF>> /View <</ViewState /OFF>>>>>>');
			$this->_out('endobj');
		}
		if (count($this->layers)) {
			ksort($this->layers);
			foreach ($this->layers as $id => $layer) {
				$this->_newobj();
				$this->layers[$id]['n'] = $this->n;
				if (isset($this->layerDetails[$id]['name']) && $this->layerDetails[$id]['name']) {
					$name = $this->layerDetails[$id]['name'];
				} else {
					$name = $layer['name'];
				}
				$this->_out('<</Type /OCG /Name ' . $this->_UTF16BEtextstring($name) . '>>');
				$this->_out('endobj');
			}
		}
	}

	/* -- IMPORTS -- */

	// from mPDFI
	function _putimportedobjects()
	{
		if (is_array($this->parsers) && count($this->parsers) > 0) {
			foreach ($this->parsers as $filename => $p) {
				$this->current_parser = $this->parsers[$filename];
				if (is_array($this->_obj_stack[$filename])) {
					while ($n = key($this->_obj_stack[$filename])) {
						$nObj = $this->current_parser->resolveObject($this->_obj_stack[$filename][$n][1]);
						$this->_newobj($this->_obj_stack[$filename][$n][0]);
						if ($nObj[0] == pdf_parser::TYPE_STREAM) {
							$this->pdf_write_value($nObj);
						} else {
							$this->pdf_write_value($nObj[1]);
						}
						$this->_out('endobj');
						$this->_obj_stack[$filename][$n] = null; // free memory
						unset($this->_obj_stack[$filename][$n]);
						reset($this->_obj_stack[$filename]);
					}
				}
			}
		}
	}

	function _putformxobjects()
	{
		$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
		reset($this->tpls);
		foreach ($this->tpls as $tplidx => $tpl) {
			$p = ($this->compress) ? gzcompress($tpl['buffer']) : $tpl['buffer'];
			$this->_newobj();
			$this->tpls[$tplidx]['n'] = $this->n;
			$this->_out('<<' . $filter . '/Type /XObject');
			$this->_out('/Subtype /Form');
			$this->_out('/FormType 1');
			// Left/Bottom/Right/Top
			$this->_out(sprintf('/BBox [%.2F %.2F %.2F %.2F]', $tpl['box']['x'] * Mpdf::SCALE, $tpl['box']['y'] * Mpdf::SCALE, ($tpl['box']['x'] + $tpl['box']['w']) * Mpdf::SCALE, ($tpl['box']['y'] + $tpl['box']['h']) * Mpdf::SCALE));


			if (isset($tpl['box'])) {
				$this->_out(sprintf('/Matrix [1 0 0 1 %.5F %.5F]', -$tpl['box']['x'] * Mpdf::SCALE, -$tpl['box']['y'] * Mpdf::SCALE));
			}
			$this->_out('/Resources ');

			if (isset($tpl['resources'])) {
				$this->current_parser = $tpl['parser'];
				$this->pdf_write_value($tpl['resources']);
			} else {
				$this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
				if (isset($this->_res['tpl'][$tplidx]['fonts']) && count($this->_res['tpl'][$tplidx]['fonts'])) {
					$this->_out('/Font <<');
					foreach ($this->_res['tpl'][$tplidx]['fonts'] as $font) {
						$this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
					}
					$this->_out('>>');
				}
				if (isset($this->_res['tpl'][$tplidx]['images']) && count($this->_res['tpl'][$tplidx]['images']) ||
					isset($this->_res['tpl'][$tplidx]['tpls']) && count($this->_res['tpl'][$tplidx]['tpls'])) {
					$this->_out('/XObject <<');
					if (isset($this->_res['tpl'][$tplidx]['images']) && count($this->_res['tpl'][$tplidx]['images'])) {
						foreach ($this->_res['tpl'][$tplidx]['images'] as $image) {
							$this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
						}
					}
					if (isset($this->_res['tpl'][$tplidx]['tpls']) && count($this->_res['tpl'][$tplidx]['tpls'])) {
						foreach ($this->_res['tpl'][$tplidx]['tpls'] as $i => $itpl) {
							$this->_out($this->tplprefix . $i . ' ' . $itpl['n'] . ' 0 R');
						}
					}
					$this->_out('>>');
				}
				$this->_out('>>');
			}

			$this->_out('/Length ' . strlen($p) . ' >>');
			$this->_putstream($p);
			$this->_out('endobj');
		}
	}

	/* -- END IMPORTS -- */

	function _putpatterns()
	{
		for ($i = 1; $i <= count($this->patterns); $i++) {
			$x = $this->patterns[$i]['x'];
			$y = $this->patterns[$i]['y'];
			$w = $this->patterns[$i]['w'];
			$h = $this->patterns[$i]['h'];
			$pgh = $this->patterns[$i]['pgh'];
			$orig_w = $this->patterns[$i]['orig_w'];
			$orig_h = $this->patterns[$i]['orig_h'];
			$image_id = $this->patterns[$i]['image_id'];
			$itype = $this->patterns[$i]['itype'];

			if (isset($this->patterns[$i]['bpa'])) {
				$bpa = $this->patterns[$i]['bpa'];
			} else {
				$bpa = []; // background positioning area
			}

			if ($this->patterns[$i]['x_repeat']) {
				$x_repeat = true;
			} else {
				$x_repeat = false;
			}

			if ($this->patterns[$i]['y_repeat']) {
				$y_repeat = true;
			} else {
				$y_repeat = false;
			}

			$x_pos = $this->patterns[$i]['x_pos'];

			if (stristr($x_pos, '%')) {
				$x_pos = (float) $x_pos;
				$x_pos /= 100;

				if (isset($bpa['w']) && $bpa['w']) {
					$x_pos = ($bpa['w'] * $x_pos) - ($orig_w / Mpdf::SCALE * $x_pos);
				} else {
					$x_pos = ($w * $x_pos) - ($orig_w / Mpdf::SCALE * $x_pos);
				}
			}

			$y_pos = $this->patterns[$i]['y_pos'];

			if (stristr($y_pos, '%')) {
				$y_pos = (float) $y_pos;
				$y_pos /= 100;

				if (isset($bpa['h']) && $bpa['h']) {
					$y_pos = ($bpa['h'] * $y_pos) - ($orig_h / Mpdf::SCALE * $y_pos);
				} else {
					$y_pos = ($h * $y_pos) - ($orig_h / Mpdf::SCALE * $y_pos);
				}
			}

			if (isset($bpa['x']) && $bpa['x']) {
				$adj_x = ($x_pos + $bpa['x']) * Mpdf::SCALE;
			} else {
				$adj_x = ($x_pos + $x) * Mpdf::SCALE;
			}

			if (isset($bpa['y']) && $bpa['y']) {
				$adj_y = (($pgh - $y_pos - $bpa['y']) * Mpdf::SCALE) - $orig_h;
			} else {
				$adj_y = (($pgh - $y_pos - $y) * Mpdf::SCALE) - $orig_h;
			}

			$img_obj = false;

			if ($itype == 'svg' || $itype == 'wmf') {
				foreach ($this->formobjects as $fo) {
					if ($fo['i'] == $image_id) {
						$img_obj = $fo['n'];
						$fo_w = $fo['w'];
						$fo_h = -$fo['h'];
						$wmf_x = $fo['x'];
						$wmf_y = $fo['y'];
						break;
					}
				}
			} else {
				foreach ($this->images as $img) {
					if ($img['i'] == $image_id) {
						$img_obj = $img['n'];
						break;
					}
				}
			}

			if (!$img_obj) {
				throw new \Mpdf\MpdfException("Problem: Image object not found for background pattern " . $img['i']);
			}

			$this->_newobj();
			$this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
			if ($itype == 'svg' || $itype == 'wmf') {
				$this->_out('/XObject <</FO' . $image_id . ' ' . $img_obj . ' 0 R >>');
				// ******* ADD ANY ExtGStates, Shading AND Fonts needed for the FormObject
				// Set in classes/svg array['fo'] = true
				// Required that _putshaders comes before _putpatterns in _putresources
				// This adds any resources associated with any FormObject to every Formobject - overkill but works!
				if (count($this->extgstates)) {
					$this->_out('/ExtGState <<');
					foreach ($this->extgstates as $k => $extgstate) {
						if (isset($extgstate['fo']) && $extgstate['fo']) {
							if (isset($extgstate['trans'])) {
								$this->_out('/' . $extgstate['trans'] . ' ' . $extgstate['n'] . ' 0 R');
							} else {
								$this->_out('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
							}
						}
					}
					$this->_out('>>');
				}
				/* -- BACKGROUNDS -- */
				if (isset($this->gradients) and ( count($this->gradients) > 0)) {
					$this->_out('/Shading <<');
					foreach ($this->gradients as $id => $grad) {
						if (isset($grad['fo']) && $grad['fo']) {
							$this->_out('/Sh' . $id . ' ' . $grad['id'] . ' 0 R');
						}
					}
					$this->_out('>>');
				}
				/* -- END BACKGROUNDS -- */
				$this->_out('/Font <<');
				foreach ($this->fonts as $font) {
					if (!$font['used'] && $font['type'] == 'TTF') {
						continue;
					}
					if (isset($font['fo']) && $font['fo']) {
						if ($font['type'] == 'TTF' && ($font['sip'] || $font['smp'])) {
							foreach ($font['n'] as $k => $fid) {
								$this->_out('/F' . $font['subsetfontids'][$k] . ' ' . $font['n'][$k] . ' 0 R');
							}
						} else {
							$this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
						}
					}
				}
				$this->_out('>>');
			} else {
				$this->_out('/XObject <</I' . $image_id . ' ' . $img_obj . ' 0 R >>');
			}
			$this->_out('>>');
			$this->_out('endobj');

			$this->_newobj();
			$this->patterns[$i]['n'] = $this->n;
			$this->_out('<< /Type /Pattern /PatternType 1 /PaintType 1 /TilingType 2');
			$this->_out('/Resources ' . ($this->n - 1) . ' 0 R');

			$this->_out(sprintf('/BBox [0 0 %.3F %.3F]', $orig_w, $orig_h));
			if ($x_repeat) {
				$this->_out(sprintf('/XStep %.3F', $orig_w));
			} else {
				$this->_out(sprintf('/XStep %d', 99999));
			}
			if ($y_repeat) {
				$this->_out(sprintf('/YStep %.3F', $orig_h));
			} else {
				$this->_out(sprintf('/YStep %d', 99999));
			}

			if ($itype == 'svg' || $itype == 'wmf') {
				$this->_out(sprintf('/Matrix [1 0 0 -1 %.3F %.3F]', $adj_x, ($adj_y + $orig_h)));
				$s = sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /FO%d Do Q", ($orig_w / $fo_w), (-$orig_h / $fo_h), -($orig_w / $fo_w) * $wmf_x, ($orig_w / $fo_w) * $wmf_y, $image_id);
			} else {
				$this->_out(sprintf('/Matrix [1 0 0 1 %.3F %.3F]', $adj_x, $adj_y));
				$s = sprintf("q %.3F 0 0 %.3F 0 0 cm /I%d Do Q", $orig_w, $orig_h, $image_id);
			}

			if ($this->compress) {
				$this->_out('/Filter /FlateDecode');
				$s = gzcompress($s);
			}
			$this->_out('/Length ' . strlen($s) . '>>');
			$this->_putstream($s);
			$this->_out('endobj');
		}
	}

	/* -- BACKGROUNDS -- */

	function _putshaders()
	{
		$maxid = count($this->gradients); // index for transparency gradients
		foreach ($this->gradients as $id => $grad) {
			if (($grad['type'] == 2 || $grad['type'] == 3) && empty($grad['is_mask'])) {
				$this->_newobj();
				$this->_out('<<');
				$this->_out('/FunctionType 3');
				$this->_out('/Domain [0 1]');
				$fn = [];
				$bd = [];
				$en = [];
				for ($i = 0; $i < (count($grad['stops']) - 1); $i++) {
					$fn[] = ($this->n + 1 + $i) . ' 0 R';
					$en[] = '0 1';
					if ($i > 0) {
						$bd[] = sprintf('%.3F', $grad['stops'][$i]['offset']);
					}
				}
				$this->_out('/Functions [' . implode(' ', $fn) . ']');
				$this->_out('/Bounds [' . implode(' ', $bd) . ']');
				$this->_out('/Encode [' . implode(' ', $en) . ']');
				$this->_out('>>');
				$this->_out('endobj');
				$f1 = $this->n;
				for ($i = 0; $i < (count($grad['stops']) - 1); $i++) {
					$this->_newobj();
					$this->_out('<<');
					$this->_out('/FunctionType 2');
					$this->_out('/Domain [0 1]');
					$this->_out('/C0 [' . $grad['stops'][$i]['col'] . ']');
					$this->_out('/C1 [' . $grad['stops'][$i + 1]['col'] . ']');
					$this->_out('/N 1');
					$this->_out('>>');
					$this->_out('endobj');
				}
			}
			if ($grad['type'] == 2 || $grad['type'] == 3) {
				if (isset($grad['trans']) && $grad['trans']) {
					$this->_newobj();
					$this->_out('<<');
					$this->_out('/FunctionType 3');
					$this->_out('/Domain [0 1]');
					$fn = [];
					$bd = [];
					$en = [];
					for ($i = 0; $i < (count($grad['stops']) - 1); $i++) {
						$fn[] = ($this->n + 1 + $i) . ' 0 R';
						$en[] = '0 1';
						if ($i > 0) {
							$bd[] = sprintf('%.3F', $grad['stops'][$i]['offset']);
						}
					}
					$this->_out('/Functions [' . implode(' ', $fn) . ']');
					$this->_out('/Bounds [' . implode(' ', $bd) . ']');
					$this->_out('/Encode [' . implode(' ', $en) . ']');
					$this->_out('>>');
					$this->_out('endobj');
					$f2 = $this->n;
					for ($i = 0; $i < (count($grad['stops']) - 1); $i++) {
						$this->_newobj();
						$this->_out('<<');
						$this->_out('/FunctionType 2');
						$this->_out('/Domain [0 1]');
						$this->_out(sprintf('/C0 [%.3F]', $grad['stops'][$i]['opacity']));
						$this->_out(sprintf('/C1 [%.3F]', $grad['stops'][$i + 1]['opacity']));
						$this->_out('/N 1');
						$this->_out('>>');
						$this->_out('endobj');
					}
				}
			}

			if (empty($grad['is_mask'])) {
				$this->_newobj();
				$this->_out('<<');
				$this->_out('/ShadingType ' . $grad['type']);
				if (isset($grad['colorspace'])) {
					$this->_out('/ColorSpace /Device' . $grad['colorspace']);  // Can use CMYK if all C0 and C1 above have 4 values
				} else {
					$this->_out('/ColorSpace /DeviceRGB');
				}
				if ($grad['type'] == 2) {
					$this->_out(sprintf('/Coords [%.3F %.3F %.3F %.3F]', $grad['coords'][0], $grad['coords'][1], $grad['coords'][2], $grad['coords'][3]));
					$this->_out('/Function ' . $f1 . ' 0 R');
					$this->_out('/Extend [' . $grad['extend'][0] . ' ' . $grad['extend'][1] . '] ');
					$this->_out('>>');
				} elseif ($grad['type'] == 3) {
					// x0, y0, r0, x1, y1, r1
					// at this this time radius of inner circle is 0
					$ir = 0;
					if (isset($grad['coords'][5]) && $grad['coords'][5]) {
						$ir = $grad['coords'][5];
					}
					$this->_out(sprintf('/Coords [%.3F %.3F %.3F %.3F %.3F %.3F]', $grad['coords'][0], $grad['coords'][1], $ir, $grad['coords'][2], $grad['coords'][3], $grad['coords'][4]));
					$this->_out('/Function ' . $f1 . ' 0 R');
					$this->_out('/Extend [' . $grad['extend'][0] . ' ' . $grad['extend'][1] . '] ');
					$this->_out('>>');
				} elseif ($grad['type'] == 6) {
					$this->_out('/BitsPerCoordinate 16');
					$this->_out('/BitsPerComponent 8');
					if ($grad['colorspace'] == 'CMYK') {
						$this->_out('/Decode[0 1 0 1 0 1 0 1 0 1 0 1]');
					} elseif ($grad['colorspace'] == 'Gray') {
						$this->_out('/Decode[0 1 0 1 0 1]');
					} else {
						$this->_out('/Decode[0 1 0 1 0 1 0 1 0 1]');
					}
					$this->_out('/BitsPerFlag 8');
					$this->_out('/Length ' . strlen($grad['stream']));
					$this->_out('>>');
					$this->_putstream($grad['stream']);
				}
				$this->_out('endobj');
			}

			$this->gradients[$id]['id'] = $this->n;

			// set pattern object
			$this->_newobj();
			$out = '<< /Type /Pattern /PatternType 2';
			$out .= ' /Shading ' . $this->gradients[$id]['id'] . ' 0 R';
			$out .= ' >>';
			$out .= "\n" . 'endobj';
			$this->_out($out);


			$this->gradients[$id]['pattern'] = $this->n;

			if (isset($grad['trans']) && $grad['trans']) {
				// luminosity pattern
				$transid = $id + $maxid;
				$this->_newobj();
				$this->_out('<<');
				$this->_out('/ShadingType ' . $grad['type']);
				$this->_out('/ColorSpace /DeviceGray');
				if ($grad['type'] == 2) {
					$this->_out(sprintf('/Coords [%.3F %.3F %.3F %.3F]', $grad['coords'][0], $grad['coords'][1], $grad['coords'][2], $grad['coords'][3]));
					$this->_out('/Function ' . $f2 . ' 0 R');
					$this->_out('/Extend [' . $grad['extend'][0] . ' ' . $grad['extend'][1] . '] ');
					$this->_out('>>');
				} elseif ($grad['type'] == 3) {
					// x0, y0, r0, x1, y1, r1
					// at this this time radius of inner circle is 0
					$ir = 0;
					if (isset($grad['coords'][5]) && $grad['coords'][5]) {
						$ir = $grad['coords'][5];
					}
					$this->_out(sprintf('/Coords [%.3F %.3F %.3F %.3F %.3F %.3F]', $grad['coords'][0], $grad['coords'][1], $ir, $grad['coords'][2], $grad['coords'][3], $grad['coords'][4]));
					$this->_out('/Function ' . $f2 . ' 0 R');
					$this->_out('/Extend [' . $grad['extend'][0] . ' ' . $grad['extend'][1] . '] ');
					$this->_out('>>');
				} elseif ($grad['type'] == 6) {
					$this->_out('/BitsPerCoordinate 16');
					$this->_out('/BitsPerComponent 8');
					$this->_out('/Decode[0 1 0 1 0 1]');
					$this->_out('/BitsPerFlag 8');
					$this->_out('/Length ' . strlen($grad['stream_trans']));
					$this->_out('>>');
					$this->_putstream($grad['stream_trans']);
				}
				$this->_out('endobj');

				$this->gradients[$transid]['id'] = $this->n;
				$this->_newobj();
				$this->_out('<< /Type /Pattern /PatternType 2');
				$this->_out('/Shading ' . $this->gradients[$transid]['id'] . ' 0 R');
				$this->_out('>>');
				$this->_out('endobj');
				$this->gradients[$transid]['pattern'] = $this->n;
				$this->_newobj();
				// Need to extend size of viewing box in case of transformations
				$str = 'q /a0 gs /Pattern cs /p' . $transid . ' scn -' . ($this->wPt / 2) . ' -' . ($this->hPt / 2) . ' ' . (2 * $this->wPt) . ' ' . (2 * $this->hPt) . ' re f Q';
				$filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
				$p = ($this->compress) ? gzcompress($str) : $str;
				$this->_out('<< /Type /XObject /Subtype /Form /FormType 1 ' . $filter);
				$this->_out('/Length ' . strlen($p));
				$this->_out('/BBox [-' . ($this->wPt / 2) . ' -' . ($this->hPt / 2) . ' ' . (2 * $this->wPt) . ' ' . (2 * $this->hPt) . ']');
				$this->_out('/Group << /Type /Group /S /Transparency /CS /DeviceGray >>');
				$this->_out('/Resources <<');
				$this->_out('/ExtGState << /a0 << /ca 1 /CA 1 >> >>');
				$this->_out('/Pattern << /p' . $transid . ' ' . $this->gradients[$transid]['pattern'] . ' 0 R >>');
				$this->_out('>>');
				$this->_out('>>');
				$this->_putstream($p);
				$this->_out('endobj');
				$this->_newobj();
				$this->_out('<< /Type /Mask /S /Luminosity /G ' . ($this->n - 1) . ' 0 R >>' . "\n" . 'endobj');
				$this->_newobj();
				$this->_out('<< /Type /ExtGState /SMask ' . ($this->n - 1) . ' 0 R /AIS false >>' . "\n" . 'endobj');
				if (isset($grad['fo']) && $grad['fo']) {
					$this->extgstates[] = ['n' => $this->n, 'trans' => 'TGS' . $id, 'fo' => true];
				} else {
					$this->extgstates[] = ['n' => $this->n, 'trans' => 'TGS' . $id];
				}
			}
		}
	}

	/* -- END BACKGROUNDS -- */

	function _putspotcolors()
	{
		foreach ($this->spotColors as $name => $color) {
			$this->_newobj();
			$this->_out('[/Separation /' . str_replace(' ', '#20', $name));
			$this->_out('/DeviceCMYK <<');
			$this->_out('/Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] ');
			$this->_out(sprintf('/C1 [%.3F %.3F %.3F %.3F] ', $color['c'] / 100, $color['m'] / 100, $color['y'] / 100, $color['k'] / 100));
			$this->_out('/FunctionType 2 /Domain [0 1] /N 1>>]');
			$this->_out('endobj');
			$this->spotColors[$name]['n'] = $this->n;
		}
	}

	function _putresources()
	{
		if ($this->hasOC || count($this->layers)) {
			$this->_putocg();
		}
		$this->_putextgstates();
		$this->_putspotcolors();

		// @log Compiling Fonts

		$this->_putfonts();

		// @log Compiling Images

		$this->_putimages();
		$this->_putformobjects(); // *IMAGES-CORE*

		/* -- IMPORTS -- */
		if ($this->enableImports) {
			$this->_putformxobjects();
			$this->_putimportedobjects();
		}
		/* -- END IMPORTS -- */

		/* -- BACKGROUNDS -- */
		$this->_putshaders();
		$this->_putpatterns();
		/* -- END BACKGROUNDS -- */


		// Resource dictionary
		$this->offsets[2] = strlen($this->buffer);
		$this->_out('2 0 obj');
		$this->_out('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');

		$this->_out('/Font <<');
		foreach ($this->fonts as $font) {
			if (isset($font['type']) && $font['type'] == 'TTF' && !$font['used']) {
				continue;
			}
			if (isset($font['type']) && $font['type'] == 'TTF' && ($font['sip'] || $font['smp'])) {
				foreach ($font['n'] as $k => $fid) {
					$this->_out('/F' . $font['subsetfontids'][$k] . ' ' . $font['n'][$k] . ' 0 R');
				}
			} else {
				$this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
			}
		}
		$this->_out('>>');

		if (count($this->spotColors)) {
			$this->_out('/ColorSpace <<');
			foreach ($this->spotColors as $color) {
				$this->_out('/CS' . $color['i'] . ' ' . $color['n'] . ' 0 R');
			}
			$this->_out('>>');
		}

		if (count($this->extgstates)) {
			$this->_out('/ExtGState <<');
			foreach ($this->extgstates as $k => $extgstate) {
				if (isset($extgstate['trans'])) {
					$this->_out('/' . $extgstate['trans'] . ' ' . $extgstate['n'] . ' 0 R');
				} else {
					$this->_out('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
				}
			}
			$this->_out('>>');
		}

		/* -- BACKGROUNDS -- */
		if ((isset($this->gradients) and ( count($this->gradients) > 0)) || ($this->enableImports && count($this->tpls))) { // mPDF 5.7.3

			$this->_out('/Shading <<');

			foreach ($this->gradients as $id => $grad) {
				$this->_out('/Sh' . $id . ' ' . $grad['id'] . ' 0 R');
			}

			// mPDF 5.7.3
			// If a shading dictionary is in an object (tpl) imported from another PDF, it needs to be included
			// in the document resources, as well as the object resources
			// Otherwise get an error in some PDF viewers
			if ($this->enableImports && count($this->tpls)) {

				foreach ($this->tpls as $tplidx => $tpl) {

					if (isset($tpl['resources'])) {

						$this->current_parser = $tpl['parser'];

						foreach ($tpl['resources'][1] as $k => $v) {
							if ($k == '/Shading') {
								foreach ($v[1] as $k2 => $v2) {
									$this->_out($k2 . " ", false);
									$this->pdf_write_value($v2);
								}
							}
						}
					}
				}
			}

			$this->_out('>>');

			/*
			  // ??? Not needed !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
			  $this->_out('/Pattern <<');
			  foreach ($this->gradients as $id => $grad) {
			  $this->_out('/P'.$id.' '.$grad['pattern'].' 0 R');
			  }
			  $this->_out('>>');
			 */
		}
		/* -- END BACKGROUNDS -- */

		if (count($this->images) || count($this->formobjects) || ($this->enableImports && count($this->tpls))) {
			$this->_out('/XObject <<');
			foreach ($this->images as $image) {
				$this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
			}
			foreach ($this->formobjects as $formobject) {
				$this->_out('/FO' . $formobject['i'] . ' ' . $formobject['n'] . ' 0 R');
			}
			/* -- IMPORTS -- */
			if ($this->enableImports && count($this->tpls)) {
				foreach ($this->tpls as $tplidx => $tpl) {
					$this->_out($this->tplprefix . $tplidx . ' ' . $tpl['n'] . ' 0 R');
				}
			}
			/* -- END IMPORTS -- */
			$this->_out('>>');
		}

		/* -- BACKGROUNDS -- */

		if (count($this->patterns)) {
			$this->_out('/Pattern <<');
			foreach ($this->patterns as $k => $patterns) {
				$this->_out('/P' . $k . ' ' . $patterns['n'] . ' 0 R');
			}
			$this->_out('>>');
		}
		/* -- END BACKGROUNDS -- */

		if ($this->hasOC || count($this->layers)) {
			$this->_out('/Properties <<');
			if ($this->hasOC) {
				$this->_out('/OC1 ' . $this->n_ocg_print . ' 0 R /OC2 ' . $this->n_ocg_view . ' 0 R /OC3 ' . $this->n_ocg_hidden . ' 0 R ');
			}
			if (count($this->layers)) {
				foreach ($this->layers as $id => $layer) {
					$this->_out('/ZI' . $id . ' ' . $layer['n'] . ' 0 R');
				}
			}
			$this->_out('>>');
		}

		$this->_out('>>');
		$this->_out('endobj'); // end resource dictionary

		$this->_putbookmarks();

		if (isset($this->js) && $this->js) {
			$this->_putjavascript();
		}

		if ($this->encrypted) {
			$this->_newobj();
			$this->enc_obj_id = $this->n;
			$this->_out('<<');
			$this->_putencryption();
			$this->_out('>>');
			$this->_out('endobj');
		}
	}

	function _putjavascript()
	{
		$this->_newobj();
		$this->n_js = $this->n;
		$this->_out('<<');
		$this->_out('/Names [(EmbeddedJS) ' . (1 + $this->n) . ' 0 R ]');
		$this->_out('>>');
		$this->_out('endobj');

		$this->_newobj();
		$this->_out('<<');
		$this->_out('/S /JavaScript');
		$this->_out('/JS ' . $this->_textstring($this->js));
		$this->_out('>>');
		$this->_out('endobj');
	}

	function _putencryption()
	{
		$this->_out('/Filter /Standard');
		if ($this->protection->getUseRC128Encryption()) {
			$this->_out('/V 2');
			$this->_out('/R 3');
			$this->_out('/Length 128');
		} else {
			$this->_out('/V 1');
			$this->_out('/R 2');
		}
		$this->_out('/O (' . $this->_escape($this->protection->getOValue()) . ')');
		$this->_out('/U (' . $this->_escape($this->protection->getUvalue()) . ')');
		$this->_out('/P ' . $this->protection->getPvalue());
	}

	function _puttrailer()
	{
		$this->_out('/Size ' . ($this->n + 1));
		$this->_out('/Root ' . $this->n . ' 0 R');
		$this->_out('/Info ' . $this->InfoRoot . ' 0 R');

		if ($this->encrypted) {
			$this->_out('/Encrypt ' . $this->enc_obj_id . ' 0 R');
			$this->_out('/ID [<' . $this->protection->getUniqid() . '> <' . $this->protection->getUniqid() . '>]');
		} else {
			$uniqid = md5(time() . $this->buffer);
			$this->_out('/ID [<' . $uniqid . '> <' . $uniqid . '>]');
		}
	}

	function _putbookmarks()
	{
		$nb = count($this->BMoutlines);
		if ($nb == 0) {
			return;
		}

		$bmo = $this->BMoutlines;
		$this->BMoutlines = [];
		$lastlevel = -1;
		for ($i = 0; $i < count($bmo); $i++) {
			if ($bmo[$i]['l'] > 0) {
				while ($bmo[$i]['l'] - $lastlevel > 1) { // If jump down more than one level, insert a new entry
					$new = $bmo[$i];
					$new['t'] = "[" . $new['t'] . "]"; // Put [] around text/title to highlight
					$new['l'] = $lastlevel + 1;
					$lastlevel++;
					$this->BMoutlines[] = $new;
				}
			}
			$this->BMoutlines[] = $bmo[$i];
			$lastlevel = $bmo[$i]['l'];
		}
		$nb = count($this->BMoutlines);

		$lru = [];
		$level = 0;
		foreach ($this->BMoutlines as $i => $o) {
			if ($o['l'] > 0) {
				$parent = $lru[$o['l'] - 1];
				// Set parent and last pointers
				$this->BMoutlines[$i]['parent'] = $parent;
				$this->BMoutlines[$parent]['last'] = $i;
				if ($o['l'] > $level) {
					// Level increasing: set first pointer
					$this->BMoutlines[$parent]['first'] = $i;
				}
			} else {
				$this->BMoutlines[$i]['parent'] = $nb;
			}
			if ($o['l'] <= $level and $i > 0) {
				// Set prev and next pointers
				$prev = $lru[$o['l']];
				$this->BMoutlines[$prev]['next'] = $i;
				$this->BMoutlines[$i]['prev'] = $prev;
			}
			$lru[$o['l']] = $i;
			$level = $o['l'];
		}


		// Outline items
		$n = $this->n + 1;
		foreach ($this->BMoutlines as $i => $o) {
			$this->_newobj();
			$this->_out('<</Title ' . $this->_UTF16BEtextstring($o['t']));
			$this->_out('/Parent ' . ($n + $o['parent']) . ' 0 R');
			if (isset($o['prev'])) {
				$this->_out('/Prev ' . ($n + $o['prev']) . ' 0 R');
			}
			if (isset($o['next'])) {
				$this->_out('/Next ' . ($n + $o['next']) . ' 0 R');
			}
			if (isset($o['first'])) {
				$this->_out('/First ' . ($n + $o['first']) . ' 0 R');
			}
			if (isset($o['last'])) {
				$this->_out('/Last ' . ($n + $o['last']) . ' 0 R');
			}


			if (isset($this->pageDim[$o['p']]['h'])) {
				$h = $this->pageDim[$o['p']]['h'];
			} else {
				$h = 0;
			}

			$this->_out(sprintf('/Dest [%d 0 R /XYZ 0 %.3F null]', 1 + 2 * ($o['p']), ($h - $o['y']) * Mpdf::SCALE));
			if (isset($this->bookmarkStyles) && isset($this->bookmarkStyles[$o['l']])) {
				// font style
				$bms = $this->bookmarkStyles[$o['l']]['style'];
				$style = 0;
				if (strpos($bms, 'B') !== false) {
					$style += 2;
				}
				if (strpos($bms, 'I') !== false) {
					$style += 1;
				}
				$this->_out(sprintf('/F %d', $style));
				// Colour
				$col = $this->bookmarkStyles[$o['l']]['color'];
				if (isset($col) && is_array($col) && count($col) == 3) {
					$this->_out(sprintf('/C [%.3F %.3F %.3F]', ($col[0] / 255), ($col[1] / 255), ($col[2] / 255)));
				}
			}

			$this->_out('/Count 0>>');
			$this->_out('endobj');
		}
		// Outline root
		$this->_newobj();
		$this->OutlineRoot = $this->n;
		$this->_out('<</Type /BMoutlines /First ' . $n . ' 0 R');
		$this->_out('/Last ' . ($n + $lru[0]) . ' 0 R>>');
		$this->_out('endobj');
	}

	/* -- END BOOKMARKS -- */

	function _setBidiCodes($mode = 'start', $bdf = '')
	{
		$s = '';
		if ($mode == 'end') {
			// PDF comes before PDI to close isolate-override (e.g. "LRILROPDFPDI")
			if (strpos($bdf, 'PDF') !== false) {
				$s .= UtfString::code2utf(0x202C);
			} // POP DIRECTIONAL FORMATTING
			if (strpos($bdf, 'PDI') !== false) {
				$s .= UtfString::code2utf(0x2069);
			} // POP DIRECTIONAL ISOLATE
		} elseif ($mode == 'start') {
			// LRI comes before LRO to open isolate-override (e.g. "LRILROPDFPDI")
			if (strpos($bdf, 'LRI') !== false) {
				$s .= UtfString::code2utf(0x2066);
			} // U+2066 LRI
			elseif (strpos($bdf, 'RLI') !== false) {
				$s .= UtfString::code2utf(0x2067);
			} // U+2067 RLI
			elseif (strpos($bdf, 'FSI') !== false) {
				$s .= UtfString::code2utf(0x2068);
			} // U+2068 FSI
			if (strpos($bdf, 'LRO') !== false) {
				$s .= UtfString::code2utf(0x202D);
			} // U+202D LRO
			elseif (strpos($bdf, 'RLO') !== false) {
				$s .= UtfString::code2utf(0x202E);
			} // U+202E RLO
			elseif (strpos($bdf, 'LRE') !== false) {
				$s .= UtfString::code2utf(0x202A);
			} // U+202A LRE
			elseif (strpos($bdf, 'RLE') !== false) {
				$s .= UtfString::code2utf(0x202B);
			} // U+202B RLE
		}
		return $s;
	}

	/* -- END OTL -- */

	//
	// ****************************
	// ****************************


	function _transform($tm, $returnstring = false)
	{
		if ($returnstring) {
			return(sprintf('%.4F %.4F %.4F %.4F %.4F %.4F cm', $tm[0], $tm[1], $tm[2], $tm[3], $tm[4], $tm[5]));
		} else {
			$this->_out(sprintf('%.4F %.4F %.4F %.4F %.4F %.4F cm', $tm[0], $tm[1], $tm[2], $tm[3], $tm[4], $tm[5]));
		}
	}

	// ===========================
	// Functions
	// Call-back function Used for usort in fn _tableWrite

	function _cmpdom($a, $b)
	{
		return ($a["dom"] < $b["dom"]) ? -1 : 1;
	}

	/* -- COLUMNS -- */

	/**
	 * Un-escapes a PDF string
	 *
	 * @param string $s
	 * @return string
	 */
	function _unescape($s)
	{
		$out = '';
		for ($count = 0, $n = strlen($s); $count < $n; $count++) {
			if ($s[$count] != '\\' || $count == $n-1) {
				$out .= $s[$count];
			} else {
				switch ($s[++$count]) {
					case ')':
					case '(':
					case '\\':
						$out .= $s[$count];
						break;
					case 'f':
						$out .= chr(0x0C);
						break;
					case 'b':
						$out .= chr(0x08);
						break;
					case 't':
						$out .= chr(0x09);
						break;
					case 'r':
						$out .= chr(0x0D);
						break;
					case 'n':
						$out .= chr(0x0A);
						break;
					case "\r":
						if ($count != $n-1 && $s[$count+1] == "\n") {
							$count++;
						}
						break;
					case "\n":
						break;
					default:
						// Octal-Values
						if (ord($s[$count]) >= ord('0') &&
							ord($s[$count]) <= ord('9')) {
							$oct = ''. $s[$count];
							if (ord($s[$count+1]) >= ord('0') &&
								ord($s[$count+1]) <= ord('9')) {
								$oct .= $s[++$count];
								if (ord($s[$count+1]) >= ord('0') &&
									ord($s[$count+1]) <= ord('9')) {
									$oct .= $s[++$count];
								}
							}
							$out .= chr(octdec($oct));
						} else {
							$out .= $s[$count];
						}
				}
			}
		}
		return $out;
	}

	/* -- END IMPORTS -- */

	// JAVASCRIPT
	function _set_object_javascript($string)
	{
		$this->_newobj();
		$this->_out('<<');
		$this->_out('/S /JavaScript ');
		$this->_out('/JS ' . $this->_textstring($string));
		$this->_out('>>');
		$this->_out('endobj');
	}

	protected function getVersionString()
	{
		$return = self::VERSION;
		$headFile = __DIR__ . '/../.git/HEAD';
		if (file_exists($headFile)) {
			$ref = file($headFile);
			$path = explode('/', $ref[0], 3);
			$branch = isset($path[2]) ? trim($path[2]) : '';
			$revFile = __DIR__ . '/../.git/refs/heads/' . $branch;
			if ($branch && file_exists($revFile)) {
				$rev = file($revFile);
				$rev = substr($rev[0], 0, 7);
				$return .= ' (' . $rev . ')';
			}
		}

		return $return;
	}
}
