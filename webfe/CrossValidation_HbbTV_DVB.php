<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function CrossValidation_HbbTV_DVB($dom,$hbbtv,$dvb)
{
    common_crossValidation();

    if($hbbtv){
        crossValidation_HbbTV_Representations($dom);
    }
    
    if($dvb){
        crossValidation_DVB_Representations($dom);
    }
}

function common_crossValidation()
{
    global $locate, $Period_arr;
    
    for($adapt_count=0; $adapt_count<sizeof($Period_arr); $adapt_count++){
        $loc = $locate . '/Adapt' . $adapt_count.'/';
        
        $Adapt=$Period_arr[$adapt_count];
        $filecount = 0;
        $files = glob($loc . "*.xml");
        if($files)
            $filecount = count($files);
        
        if(!($opfile = fopen($locate."/Adapt".$adapt_count."_compInfo.txt", 'a'))){
            echo "Error opening/creating HbbTV/DVB Cross representation validation file: "."./Adapt".$adapt_count."_compInfo.txt";
            return;
        }
        
        if(file_exists($loc)){
       
        }

        fprintf($opfile, "\n-----Conformance checks completed----- ");
        fclose($opfile);
    }
}

function crossValidation_DVB_Representations($dom){
    global $locate, $Period_arr, $string_info;
    
    for($i=0; $i<sizeof($Period_arr); $i++){
        $loc = $locate . '/Adapt' . $i . '/';
        $opfile = fopen($locate."/Adapt".$adapt_count."_compInfo.txt", 'a');
        
        $rep_files = glob($loc . '*.xml');
        $count = count($rep_files);
        
        for($r=0; $r<$count; $r++){
            $xml_r = xmlFileLoad($rep_files[$r]);
            
            for($d=$r+1; $d<$count; $d++){
                $xml_d = xmlFileLoad($rep_files[$d]);
                DVB_compareRepresentations($dom, $opfile, $xml_r, $xml_d, $i, $r, $d);
            }
        }
    }
}

function DVB_compareRepresentations($dom, $opfile, $xml_r, $xml_d, $i, $r, $d){
    ## Section 4.3 checks for sample entry type and track_ID
    $hdlr_r = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_r = $hdlr_r->getAttribute('handler_type');
    $sdType_r = $xml_r->getElementsByTagName($hdlr_type_r.'_sampledescription')->item(0)->getAttribute('sdType');
    
    $hdlr_d = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_d = $hdlr_d->getAttribute('handler_type');
    $sdType_d = $xml_d->getElementsByTagName($hdlr_type_d.'_sampledescription')->item(0)->getAttribute('sdType');
    
        ## Non-switchable audia representation reporting
    if($sdType_r != $sdType_d){
        fwrite($opfile, "###'DVB check violated: Section 4.3- All the initialization segments for Representations within an Adaptation Set SHALL have the same sample entry type', found $sdType_r in Adaptation Set " . ($i+1) . " Representation " . ($r+1) . " $sdType_d in Adaptation Set " . ($i+1) . " Representation " . ($d+1) . ".\n");
    
        if($hdlr_type_r == $hdlr_type_d && $hdlr_type_r == 'soun')
            fwrite($opfile, "Warning for DVB check: 'Non-switchable audio codecs SHOULD NOT be present within the same Adaptation Set for the presence of consistent Representations within an Adaptation Set ', found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    }
        ##
    
    $tkhd_r = $xml_r->getElementsByTagName('tkhd')->item(0);
    $track_ID_r = $tkhd_r->getAttribute('trackID');
    $tfhds_r = $xml_r->getElementsByTagName('tfhd');
    
    $tkhd_d = $xml_d->getElementsByTagName('tkhd')->item(0);
    $track_ID_d = $tkhd_d->getAttribute('trackID');
    $tfhds_d = $xml_d->getElementsByTagName('tfhd');
    
    $tfhd_info = '';
    foreach($tfhds_r as $index => $tfhd_r){
        if($tfhd_r->getAttribute('trackID') != $tfhds_d->item($index)->getAttribute('trackID'))
            $tfhd_info .= ' error'; 
    }
    
    if($tfhd_info != '' || $track_ID_r != $track_ID_d)
        fwrite($opfile, "###'DVB check violated: Section 4.3- All Representations within an Adaptation Set SHALL have the same track_ID', not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    ##
    
    ## Section 5.1.2 check for initialization segment identicalness
    if($sdType_r == $sdType_d && ($sdType_r == 'avc1' || $sdType_r == 'avc2')){
        $stsd_r = $xml_r->getElementsByTagName('stsd')->item(0);
        $stsd_d = $xml_d->getElementsByTagName('stsd')->item(0);
        
        if(!nodes_equal($stsd_r, $stsd_d))
            fwrite($opfile, "###'DVB check violated: Section 5.1.2- In this case (content offered using either of the 'avc1' or 'avc2' sample entries), the Initialization Segment SHALL be common for all Representations within an Adaptation Set', not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    }
    ##
    
    ## Section 8.3 check for default_KID value
    $tenc_r = $xml_r->getElementsByTagName('tenc')->item(0);
    $tenc_d = $xml_d->getElementsByTagName('tenc')->item(0);
    
    if($tenc_r->length != 0 && $tenc_d->length != 0){
        if($tenc_r->getAttribute('default_KID') != $tenc_d->getAttribute('default_KID')){
            fwrite($opfile, "###'DVB check violated: Section 8.3- All Representations (in the same Adaptation Set) SHALL have the same value of 'default_KID' in their 'tenc' boxes in their Initialization Segments', not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
            
            $vide_r = $xml_r->getElementsByTagName($hdlr_type_r.'_sampledescription')->item(0);
            $vide_d = $xml_d->getElementsByTagName($hdlr_type_r.'_sampledescription')->item(0);
            $width_r = $vide_r->getAttribute('width');
            $height_r = $vide_r->getAttribute('height');
            $width_d = $vide_r->getAttribute('width');
            $height_d = $vide_r->getAttribute('height');
            
            if(($width_r < 1280 && $height_r < 720 && $width_d >= 1280 && $height_d >= 720) || ($width_d < 1280 && $height_d < 720 && $width_r >= 1280 && $height_r >= 720))
                fwrite ($opfile, "###'DVB check violated: Section 8.3- In cases where HD and SD content are contained in one presentation and MPD, but different licence rights are given for each resolution, then they SHALL be contained in different HD and SD Adaptation Sets', but SD and HD contents are contained the same adaptation set: Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        }
    }
    ##
    
    ## Section 10.4 check for audio switching
    if($hdlr_type_r == 'soun' && $hdlr_type_d == 'soun'){
        $MPD = $dom->getElementsByTagName('MPD')->item(0);
        $adapt = $MPD->getElementsByTagName('AdaptationSet')->item($i);
        $rep_r = $adapt->getElementsByTagName('Representation')->item($r);
        $rep_d = $adapt->getElementsByTagName('Representation')->item($d);
        
        $att_r = $rep_r->attributes;
        $att_d = $rep_d->attributes;
        if($att_r->length != $att_d->length)
            fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attributes found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        else{
            for($a=0; $a<$att_r->length; $a++){
                if($att_r->item($a)->name != $att_d->item($a)->name)
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attributes found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                else{
                    if($att_r->item($a)->name != 'bandwidth' && $att_r->item($a)->name != 'id'){
                        if($att_r->item($a)->value != $att_d->item($a)->value)
                            fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                    }
                }
            }
        }
        
        ## Section 6.1.1 Table 3 cross-checks for audio representations
        // @mimeType
        $adapt_mime = $adapt->getAttribute('mimeType');
        $rep_mime_r = $rep_r->getAttribute('mimeType');
        $rep_mime_d = $rep_d->getAttribute('mimeType');
        if($adapt_mime == ''){
            if($rep_mime_r != $rep_mime_d)
                fwrite($opfile, "###'DVB check violated: Section 6.1.1- @mimeType attribute SHALL be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        }
        
        // @codecs
        $adapt_codecs = $adapt->getAttribute('codecs');
        $rep_codecs_r = $rep_r->getAttribute('codecs');
        $rep_codecs_d = $rep_d->getAttribute('codecs');
        if($adapt_codecs == ''){
            if(($rep_codecs_r != '' && $rep_codecs_d != '') && $rep_codecs_r != $rep_codecs_d)
                fwrite($opfile, "Warning for DVB check: Section 6.1.1- @codecs attribute SHOULD be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        }
        
        // @audioSamplingRate
        $adapt_audioSamplingRate = $adapt->getAttribute('audioSamplingRate');
        $rep_audioSamplingRate_r = $rep_r->getAttribute('audioSamplingRate');
        $rep_audioSamplingRate_d = $rep_d->getAttribute('audioSamplingRate');
        if($adapt_audioSamplingRate == ''){
            if(($rep_audioSamplingRate_r != '' && $rep_audioSamplingRate_d != '') && $rep_audioSamplingRate_r != $rep_audioSamplingRate_d)
                fwrite($opfile, "Warning for DVB check: Section 6.1.1- @audioSamplingRate attribute SHOULD be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        }
        
        // AudioChannelConfiguration and Role
        $adapt_audioChConf = array();
        $adapt_role = array();
        foreach($adapt->childNodes as $adapt_ch){
            if($adapt_ch->nodeName == 'AudioChannelConfiguration')
                $adapt_audioChConf[] = $adapt_ch;
            elseif($adapt_ch->nodeName == 'Role')
                $adapt_role[] = $adapt_ch;
        }
        
        if(empty($adapt_audioChConf)){
            $rep_audioChConf_r = array();
            $rep_audioChConf_d = array();
            foreach($rep_r->childNodes as $rep_r_ch){
                if($rep_r_ch->nodeName == 'AudioChannelConfiguration')
                    $rep_audioChConf_r[] = $rep_r_ch;
            }
            foreach($rep_d->childNodes as $rep_d_ch){
                if($rep_d_ch->nodeName == 'AudioChannelConfiguration')
                    $rep_audioChConf_d[] = $rep_d_ch;
            }
            
            if(!empty($rep_audioChConf_r) && !empty($rep_audioChConf_d)){
                $equal_info = '';
                if($rep_audioChConf_r->length != $rep_audioChConf_d->length)
                    fwrite($opfile, "Warning for DVB check: Section 6.1.1- AudioChannelConfiguration SHOULD be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                else{
                    for($racc=0; $racc<$rep_audioChConf_r->length; $racc++){
                        $rep_audioChConf_r_i = $rep_audioChConf_r->item($racc);
                        $rep_audioChConf_d_i = $rep_audioChConf_d->item($racc);
                        
                        if(!nodes_equal($rep_audioChConf_r_i, $rep_audioChConf_d_i))
                            $equal_info .= 'no';
                    }
                }
                
                if($equal_info != '')
                    fwrite($opfile, "Warning for DVB check: Section 6.1.1- AudioChannelConfiguration SHOULD be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
            }
        }
        
        if(empty($adapt_role)){
            $rep_role_r = array();
            $rep_role_d = array();
            foreach($rep_r->childNodes as $rep_r_ch){
                if($rep_r_ch->nodeName == 'Role')
                    $rep_role_r[] = $rep_r_ch;
            }
            foreach($rep_d->childNodes as $rep_d_ch){
                if($rep_d_ch->nodeName == 'Role')
                    $rep_role_d[] = $rep_d_ch;
            }
            
            if(!empty($rep_role_r) && !empty($rep_role_d)){
                $equal_info = '';
                if($rep_role_r->length != $rep_role_d->length)
                    fwrite($opfile, "###'DVB check violated: Section 6.1.1- Role element SHALL be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                else{
                    for($rr=0; $rr<$rep_role_r->length; $rr++){
                        $rep_role_r_i = $rep_role_r->item($rr);
                        $rep_role_d_i = $rep_role_d->item($rr);
                        
                        if(!nodes_equal($rep_role_r_i, $rep_role_d_i))
                            $equal_info .= 'no';
                    }
                }
                
                if($equal_info != '')
                    fwrite($opfile, "###'DVB check violated: Section 6.1.1- Role element SHALL be common between all audio Representations in an Adaptation Set', not common in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
            }
        }
        ##
        
        ## Adaptation Set check for consistent representations: Highlight 5.1 audio and 2.0 Audio in the same adaptation set
        $soun_r = $xml_r->getElementsByTagName('soun_sampledescription')->item(0);
        $conf_r = $soun_r->getElementsByTagName('DecoderSpecificInfo')->item(0);
        $conf_atts_r = $conf_r->attributes;
        $conf_aud_r = '';
        foreach($conf_atts_r as $conf_att_r){
            if(strpos($conf_att_r->value, 'config is') !== FALSE)
                $conf_aud_r = $conf_att_r->value;
        }
        
        $soun_d = $xml_d->getElementsByTagName('soun_sampledescription')->item(0);
        $conf_d = $soun_d->getElementsByTagName('DecoderSpecificInfo')->item(0);
        $conf_atts_d = $conf_d->attributes;
        $conf_aud_d = '';
        foreach($conf_atts_d as $conf_att_d){
            if(strpos($conf_att_d->value, 'config is') !== FALSE)
                $conf_aud_d = $conf_att_d->value;
        }
        
        if($conf_aud_r != '' && $conf_aud_d != ''){
            if(($conf_aud_r == 'config is 5+1' && $conf_aud_d == 'config is stereo') || ($conf_aud_d == 'config is 5+1' && $conf_aud_r == 'config is stereo'))
                fwrite($opfile, "Warning for DVB check: '5.1 Audio and 2.0 Audio SHOULD NOT be present within the same Adaptation Set for the presence of consistent Representations within an Adaptation Set ', found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        }
        ##
    }
    ##
    
    ## Section 10.4 check for video switching
    if($hdlr_type_r == 'vide' && $hdlr_type_d == 'vide'){
        $MPD = $dom->getElementsByTagName('MPD')->item(0);
        $adapt = $MPD->getElementsByTagName('AdaptationSet')->item($i);
        $rep_r = $adapt->getElementsByTagName('Representation')->item($r);
        $rep_d = $adapt->getElementsByTagName('Representation')->item($d);
        
        $att_r = $rep_r->attributes;
        $att_d = $rep_d->attributes;
        if($att_r->length != $att_d->length)
            fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attributes found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
        else{
            for($a=0; $a<$att_r->length; $a++){
                if($att_r->item($i)->name != $att_d->item($i)->name)
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attributes found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                else{
                    if($att_r->item($a)->name != 'bandwidth' && $att_r->item($a)->name != 'id' && $att_r->item($a)->name != 'frameRate' && $att_r->item($a)->name != 'width' && $att_r->item($a)->name != 'height' && $att_r->item($a)->name != 'codecs'){
                        if($att_r->item($a)->value != $att_d->item($a)->value)
                            fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                    }
                }
            }
            
            // Frame rate
            $possible_fr1 = array('25', '25/1', '50', '50/1');
            $possible_fr2 = array('30/1001', '60/1001');
            $possible_fr3 = array('30', '30/1', '60', '60/1');
            $possible_fr4 = array('24', '24/1', '48', '48/1');
            $possible_fr5 = array('24/1001');
            $fr_r = $rep_r->getAttribute('frameRate');
            $fr_d = $rep_d->getAttribute('frameRate');
            if($fr_r != '' && $fr_d != ''){
                if((in_array($fr_r, $possible_fr1) && !in_array($fr_d, $possible_fr1)) || (!in_array($fr_r, $possible_fr1) && in_array($fr_d, $possible_fr1)))
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                if((in_array($fr_r, $possible_fr2) && !in_array($fr_d, $possible_fr2)) || (!in_array($fr_r, $possible_fr2) && in_array($fr_d, $possible_fr2)))
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                if((in_array($fr_r, $possible_fr3) && !in_array($fr_d, $possible_fr3)) || (!in_array($fr_r, $possible_fr3) && in_array($fr_d, $possible_fr3)))
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                if((in_array($fr_r, $possible_fr4) && !in_array($fr_d, $possible_fr4)) || (!in_array($fr_r, $possible_fr4) && in_array($fr_d, $possible_fr4)))
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                if((in_array($fr_r, $possible_fr5) && !in_array($fr_d, $possible_fr5)) || (!in_array($fr_r, $possible_fr5) && in_array($fr_d, $possible_fr5)))
                    fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
            }
            
            // Resolution
            $width_r = $rep_r->getAttribute('width');
            $height_r = $rep_r->getAttribute('height');
            $width_d = $rep_d->getAttribute('width');
            $height_d = $rep_d->getAttribute('height');
            if($width_r != '' && $height_r != '' && $width_d != '' && $height_d != ''){
                if($adapt->getAttribute('par') != ''){
                    $par = $adapt->getAttribute('par');
                    if($width_r != $width_d || $height_r != $height_d){
                        $par_arr = explode(':', $par);
                        $par_ratio = (float)$par_arr[0] / (float)$par_arr[1];
                        
                        $par_r = $width_r/$height_r;
                        $par_d = $width_d/$height_d;
                        if($par_r != $par_d || $par_r != $par_ratio || $par_d != $par_ratio)
                            fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                    }
                }
                else{
                    $content_comps = $adapt->getEelementsByTagName('ContentComponent');
                    foreach($content_comps as $content_comp){
                        $pars[] = $content_comp->getAttribute('par');
                    }
                    
                    if(array_unique($pars) != 1 || array_unique($pars) == 1 && in_array('', $pars))
                        fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                    
                    elseif(array_unique($pars) == 1 && !in_array('', $pars)){
                        if($width_r != $width_d || $height_r != $height_d){
                            $par = $pars[0];
                            $par_arr = explode(':', $par);
                            $par_ratio = (float)$par_arr[0] / (float)$par_arr[1];
                            
                            $par_r = $width_r/$height_r;
                            $par_d = $width_d/$height_d;
                            if($par_r != $par_d || $par_r != $par_ratio || $par_d != $par_ratio)
                                fwrite($opfile, "Information on DVB conformance: Section 10.4- 'Players SHALL support seamless swicthing between audio Representations which only differ in bit rate', different attribute values found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
                        }
                    }
                }
            }
        }
    }
    ##
}

// Check if the nodes and their descendandts are the same
function nodes_equal($node_1, $node_2){
    $equal = true;
    foreach($node_1->childNodes as $index => $ch_1){
        $ch_2 = $node_2->childNodes->item($index);
        
        if($ch_1->nodeType == XML_ELEMENT_NODE && $ch_2->nodeType == XML_ELEMENT_NODE){
            if($ch_1->nodeName != $ch_2->nodeName){
                $equal = false;
                break;
            }
           
            $atts_1 = $ch_1->attributes;
            $atts_2 = $ch_2->attributes;
            if($atts_1->length != $atts_2->length){
                $equal = false;
                break;
            }
            for($i=0; $i<$atts_1->length; $i++){
                if($atts_1->item($i)->name != $atts_2->item($i)->name || $atts_1->item($i)->value != $atts_2->item($i)->value){
                    $equal = false;
                    break;
                }
            }
            
            $equal = nodes_equal($ch_1, $ch_2);
            if($equal == false)
                break;
        }
    }
    
    return $equal;
}