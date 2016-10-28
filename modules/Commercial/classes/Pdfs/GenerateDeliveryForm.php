<?php

namespace Igestis\Modules\Commercial\Pdfs;

use \Igestis\Modules\Commercial\ConfigModuleVars;

/**
 * This class allow to generate 
 *
 * @author Gilles Hemmerlé
 */
class GenerateDeliveryForm extends GenerateCommercialDocument {
    
    private $document;
    
    /**
     *
     * @var \commercialDeliveryForm 
     */
    private $commercialDeliveryForm;
    
    
    /**
     * 
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param \CommercialCommercialDocument $document
     * @param Igestis\Modules\Keytours\HtmlRendererInterface $htmlRenderer
     * @param string $htmlRendererFile
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager, \CommercialCommercialDocument $document = null, $htmlRenderer = null, $htmlRendererFile = null) {
        parent::__construct($entityManager, $document, $htmlRenderer, $htmlRendererFile);
        $this->document = $document;
        $this->saveMode(true);
    }    
    
    /**
     * 
     * @return \Igestis\Modules\Commercial\Pdfs\GenerateDeliveryForm
     */
    public function generate() {
        
        $autoIncrement = $this->entityManager->getRepository("CommercialAutoIncrement")->find($this->document->getCompany()->getId());

        if($autoIncrement == null) {
            $autoIncrement = new \CommercialAutoIncrement($this->document->getCompany());
        }

        // Create the delivery form object
        $this->commercialDeliveryForm = new \CommercialDeliveryForm($this->document);
        
        $this->commercialDeliveryForm
             ->setPathPdfFile('')
             ->setDeliveryFormNumber($autoIncrement->getNextDeliveryFormId());

        $autoIncrement->incrementDeliveryFormId();

        $hook = \Igestis\Utils\Hook::getInstance();
        $hookParameters = new \Igestis\Types\HookParameters();
        $hookParameters->set('entityManager', $this->entityManager);
        $hookParameters->set('commercialAutoIncrement', $autoIncrement);
        $hook->callHook("afterDeliveryFormAutoIncrement", $hookParameters);
        
        if($this->saveMode) {
            if($autoIncrement) $this->entityManager->persist($autoIncrement);
            $this->entityManager->persist($this->commercialDeliveryForm);
            $this->entityManager->flush();
        }
        
         // Generate pdf
        $this->tcpdfObject = new TcpdfWithHeaderAndFooter(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // Set default font
        $this->tcpdfObject->SetFont('helvetica', '', 9);
        //$this->tcpdfObject->setPrintHeader(false);
        //$this->tcpdfObject->setPrintFooter(false);
        
        // set margins
        $this->tcpdfObject->SetMargins(PDF_MARGIN_LEFT, 80, PDF_MARGIN_RIGHT);
        $this->tcpdfObject->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->tcpdfObject->SetFooterMargin(PDF_MARGIN_FOOTER);
        // set auto page breaks
        $this->tcpdfObject->SetAutoPageBreak(TRUE, 40);
        
        $this->addReplacements(
            array(
                'commercialDeliveryForm' => $this->commercialDeliveryForm,
                "numPage" => $this->tcpdfObject->getAliasNumPage(),
                "nbPages" => $this->tcpdfObject->getAliasNbPages(),
                "companyConfig" => $this->entityManager->getRepository('CommercialCompanyConfig')->find($this->commercialDeliveryForm->getCommercialDocument()->getCompany()->getId())
            )
        );
        //Define and run Header and Footer settings.
        $this->tcpdfObject->setHeaderContent($this->generateHeader());
        $this->tcpdfObject->setFooterContent($this->generateFooter());
        
        $this->tcpdfObject->AddPage();
        $this->tcpdfObject->writeHTML($this->generateHtml());
        
        $terms = $this->generateTerms();
        if($terms) {
            $this->tcpdfObject->SetPrintHeader(false);
            $this->tcpdfObject->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $this->tcpdfObject->AddPage();
            
            $this->tcpdfObject->SetPrintFooter(false);
            $this->tcpdfObject->writeHTML($terms);
        }
        
        return $this;
    }    
    
    /**
     * Filename and mode only used if savedMode  is disabled
     * @param type $filename
     * @param type $mode
     */
    public function show($filename = 'fichier.pdf', $mode = 'D') {
        if($this->saveMode) {
            // Create quotation root folder if not exist
            if(!is_dir(ConfigModuleVars::deliveryFormFolder())) {
                if(!mkdir(ConfigModuleVars::deliveryFormFolder())) {
                    throw new \Exception(sprintf(\Igestis\I18n\Translate::_("Unable to create the delivery form folder '%s'"), ConfigModuleVars::deliveryFormFolder()));
                }
            }
            
            // Create the quotation folder for the needed company if not exist
            $companyQuotationFolder = ConfigModuleVars::deliveryFormFolder() . "/" . $this->document->getCompany()->getId();
            if(!is_dir($companyQuotationFolder)) {
                if(!mkdir($companyQuotationFolder)) {
                    throw new \Exception(sprintf(\Igestis\I18n\Translate::_("Unable to create the delivery form folder '%s'"), $companyQuotationFolder));
                }
            }
            
            $filename = $companyQuotationFolder . "/" . $this->commercialDeliveryForm->getDeliveryFormNumber() . ".pdf";
            //$mode = "FD";
            
            $fileInfos = pathinfo($filename);  
            $this->commercialDeliveryForm->setPathPdfFile($fileInfos['basename']);
            $this->entityManager->persist($this->commercialDeliveryForm);
            $this->entityManager->flush();
        }
        
        parent::show($filename, $mode);
    }
}
