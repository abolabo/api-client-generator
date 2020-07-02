<?php

namespace DoclerLabs\ApiClientGenerator\Output\Php;

use DoclerLabs\ApiClientGenerator\Output\Printer;
use PhpParser\PrettyPrinterAbstract;

class PhpPrinter
{
    private PrettyPrinterAbstract $marshaller;
    private Printer               $printer;
    private PhpCodeStyleFixer     $codeStyleFixer;

    public function __construct(PrettyPrinterAbstract $marshaller, Printer $printer, PhpCodeStyleFixer $codeStyleFixer)
    {
        $this->marshaller     = $marshaller;
        $this->printer        = $printer;
        $this->codeStyleFixer = $codeStyleFixer;
    }

    public function createFiles(PhpFileCollection $files): void
    {
        foreach ($files as $file) {
            $path = sprintf('%s/%s', $files->getBaseDirectory(), $file->getFileName());

            $this->printer->print($path, $this->marshaller->prettyPrintFile($file->getNodes()));
            $this->codeStyleFixer->fix($path);
        }
    }
}
