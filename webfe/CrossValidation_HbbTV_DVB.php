<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
function CrossValidation_HbbTV_DVB($hbbtv,$dvb)
{
    $opfile = fopen($locate."/Adapt".$adapt_count."_compInfo.txt", 'a');
    
    if($hbbtv){
        crossValidation_HbbTV_Representations($opfile);
        crossValidation_HbbTV_Segments($opfile);
    }
    
    if($dvb){
        crossValidation_DVB_Representations($opfile);
        crossValidation_DVB_Segments($opfile);
    }
}

function crossValidation_DVB_Representations($opfile){
    global $locate, $Period_arr, $string_info;
    
    for($i=0; $i<sizeof($Period_arr); $i++){
        $loc = $locate . '/Adapt' . $i . '/';
        
        $rep_files = glob($loc . '*.xml');
        $count = count($rep_files);
        
        for($r=0; $r<$count; $r++){
            $xml_r = xmlFileLoad($rep_files[$r]);
            
            for($d=$r+1; $d<$count; $d++){
                $xml_d = xmlFileLoad($rep_files[$d]);
                DVB_compareRepresentations($opfile, $xml_r, $xml_d, $i, $r, $d);
            }
        }
    }
}

function DVB_compareRepresentations($opfile, $xml_r, $xml_d, $i, $r, $d){
    ## Section 4.3 checks for sample entry type and track_ID
    $hdlr_r = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_r = $hdlr_r->getAttribute('handler_type');
    $sdType_r = $xml_r->getElementsByTagName($hdlr_type_r.'_sampledescription')->item(0)->getAttribute('sdType');
    
    $hdlr_d = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_d = $hdlr_d->getAttribute('handler_type');
    $sdType_d = $xml_d->getElementsByTagName($hdlr_type_d.'_sampledescription')->item(0)->getAttribute('sdType');
    
    if($sdType_r != $sdType_d)
        fwrite ($opfile, "###'DVB check violated: Section 4.3- All the initialization segments for Representations within an Adaptation Set SHALL have the same sample entry type, found $sdType_r in Adaptation Set " . ($i+1) . " Representation " . ($r+1) . " $sdType_d in Adaptation Set " . ($i+1) . " Representation " . ($d+1) . ".\n");
    
    $trex_r = $xml_r->getElementsByTagName('trex')->item(0);
    $track_ID_r = $trex_r->getAttribute('trackID');
    $tfhds_r = $xml_r->getElementsByTagName('tfhd');
    
    $trex_d = $xml_d->getElementsByTagName('trex')->item(0);
    $track_ID_d = $trex_d->getAttribute('trackID');
    $tfhds_d = $xml_d->getElementsByTagName('tfhd');
    
    $tfhd_info = '';
    foreach($tfhds_r as $index => $tfhd_r){
        if($tfhd_r->getAttribute('trackID') != $tfhds_d->item($index)->getAttribute('trackID'))
            $tfhd_info .= ' error'; 
    }
    
    if($tfhd_info != '' || $track_ID_r != $track_ID_d)
        fwrite ($opfile, "###'DVB check violated: Section 4.3- All Representations within an Adaptation Set SHALL have the same track_ID, not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    ##
    
    ## Section 5.1.2 check for initialization segment identicalness
    if($sdType_r == $sdType_d && ($sdType_r == 'avc1' || $sdType_r == 'avc2')){
        $stsd_r = $xml_r->getElementsByTagName('stsd')->item(0);
        $stsd_d = $xml_d->getElementsByTagName('stsd')->item(0);
        
        foreach($stsd_r->childNodes as $index => $ch_r){
            $ch_d = $stsd_d->childNodes->item($index);
            
        }
    }
    ##
}