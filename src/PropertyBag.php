<?php

namespace Mpdf;

final class PropertyBag
{
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






    //These are loaded magically from ConfigVariables
    public $useDictionaryLBR;
    public $useTibetanLBR;
    public $list_indent_default_mpdf;
    public $default_lineheight_correction;

    //Created by Tag/Table.php
    public $table;
    public $cell;
}
