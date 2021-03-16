<?php

namespace LynX39\LaraPdfMerger;

use Exception;
use TCPDI;

require_once(__DIR__ .'/tcpdf/tcpdf.php');
require_once(__DIR__ .'/tcpdf/tcpdi.php');

class PdfManage
{
    private $_files;    //['form.pdf']  ["1,2,4, 5-19"]
    private $_fpdi;

    public function init(){
        $this->_files = null;

        $this->_fpdi = new TCPDI;
        $this->_fpdi->setPrintHeader(false);
        $this->_fpdi->setPrintFooter(false);

        return $this;
    }

    /**
     * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
     * @param $filepath
     * @param $pages
     * @return PdfManage
     * @throws Exception
     */
    public function addPDF($filepath, $pages = 'all', $orientation = null)
    {
        if (file_exists($filepath)) {
            if (strtolower($pages) != 'all') {
                $pages = $this->_rewritepages($pages);
            }

            $this->_files[] = array($filepath, $pages, $orientation);
        } else {
            throw new Exception("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param $orientation
     * @param array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     * @param bool $duplex merge with
     * @throws Exception
     * @array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     */
    private function doMerge($orientation = null, $meta = [], $duplex=false)
    {
        if (!isset($this->_files) || !is_array($this->_files)) {
            throw new Exception("No PDFs to merge.");
        }

        // setting the meta tags
        if (!empty($meta)) {
            $this->setMeta($meta);
        }

        // merger operations
        foreach ($this->_files as $file) {
            $filename = $file[0];
            $filepages = $file[1];
            $fileorientation = (!is_null($file[2])) ? $file[2] : $orientation;

            $count = $this->_fpdi->setSourceFile($filename);

            //add the pages
            if ($filepages == 'all') {
                for ($i = 1; $i <= $count; $i++) {
                    $template = $this->_fpdi->importPage($i);
                    $size = $this->_fpdi->getTemplateSize($template);

                    if ($orientation == null) $fileorientation = $size['w'] < $size['h'] ? 'P' : 'L';

                    $this->_fpdi->AddPage($fileorientation, array($size['w'], $size['h']));
                    $this->_fpdi->useTemplate($template);
                }
            } else {
                foreach ($filepages as $page) {
                    if (!$template = $this->_fpdi->importPage($page)) {
                        throw new Exception("Could not load page '$page' in PDF '$filename'. Check that the page exists.");
                    }
                    $size = $this->_fpdi->getTemplateSize($template);

                    if ($orientation == null) $fileorientation = $size['w'] < $size['h'] ? 'P' : 'L';

                    $this->_fpdi->AddPage($fileorientation, array($size['w'], $size['h']));
                    $this->_fpdi->useTemplate($template);

                }
            }
            if ($duplex && $this->_fpdi->PageNo() % 2) {
                $this->_fpdi->AddPage($fileorientation, [$size['w'], $size['h']]);
            }
        }
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param string $orientation
     *
     * @return void
     *
     * @throws \Exception if there are no PDFs to merge
     */
    public function merge($orientation = null, $meta = []) {
        $this->doMerge($orientation, $meta, false);
    }

    /**
     * Merges your provided PDFs and adds blank pages between documents as needed to allow duplex printing
     * @param string $orientation
     *
     * @return void
     *
     * @throws \Exception if there are no PDFs to merge
     */
    public function duplexMerge($orientation = null, $meta = []) {
        $this->doMerge($orientation, $meta, true);
    }

    public function save($outputpath = 'newfile.pdf', $outputmode = 'file')
    {
        //output operations
        $mode = $this->_switchmode($outputmode);

        if ($mode == 'S') {
            return $this->_fpdi->Output($outputpath, 'S');
        } else {
            if ($this->_fpdi->Output($outputpath, $mode) == '') {
                return true;
            } else {
                throw new Exception("Error outputting PDF to '$outputmode'.");
            }
        }


    }

    /**
     * FPDI uses single characters for specifying the output location. Change our more descriptive string into proper format.
     * @param $mode
     * @return Character
     */
    private function _switchmode($mode)
    {
        switch(strtolower($mode))
        {
            case 'download':
                return 'D';
                break;
            case 'browser':
                return 'I';
                break;
            case 'file':
                return 'F';
                break;
            case 'string':
                return 'S';
                break;
            default:
                return 'I';
                break;
        }
    }

    /**
     * Takes our provided pages in the form of 1,3,4,16-50 and creates an array of all pages
     * @param $pages
     * @return array
     * @throws Exception
     */
    private function _rewritepages($pages)
    {
        $pages = str_replace(' ', '', $pages);
        $part = explode(',', $pages);

        //parse hyphens
        foreach ($part as $i) {
            $ind = explode('-', $i);

            if (count($ind) == 2) {
                $x = $ind[0]; //start page
                $y = $ind[1]; //end page

                if ($x > $y) {
                    throw new Exception("Starting page, '$x' is greater than ending page '$y'.");
                }

                //add middle pages
                while ($x <= $y) {
                    $newpages[] = (int) $x;
                    $x++;
                }
            } else {
                $newpages[] = (int) $ind[0];
            }
        }

        return $newpages;
    }

    /**
     * Set your meta data in merged pdf
     * @param array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     * @return TCPDI $fpdi
     */
    protected function setMeta($meta)
    {
        foreach ($meta as $key => $arg) {
            $metodName = 'set' . ucfirst($key);
            if (method_exists($this->_fpdi, $metodName)) {
                $this->_fpdi->$metodName($arg);
            }
        }
    } 

}
