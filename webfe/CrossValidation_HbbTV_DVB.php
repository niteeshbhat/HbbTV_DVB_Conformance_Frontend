<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function CrossValidation_HbbTV_DVB($dom,$hbbtv,$dvb)
{
    common_crossValidation($dom,$hbbtv,$dvb);
}

function common_crossValidation($dom,$hbbtv,$dvb)
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
        
        for($r=0; $r<$filecount; $r++){
            $xml_r = xmlFileLoad($files[$r]);
            
            for($d=$r+1; $d<$filecount; $d++){
                $xml_d = xmlFileLoad($files[$d]);
                
                if($hbbtv){
                    crossValidation_HbbTV_Representations($dom, $opfile, $xml_r, $xml_d, $adapt_count, $r, $d);
                }
                if($dvb){
                    crossValidation_DVB_Representations($dom, $opfile, $xml_r, $xml_d, $adapt_count, $r, $d);
                }
            }
        }
        init_seg_commonCheck($files,$opfile);
        
        if(file_exists($loc)){
       
        }

        fprintf($opfile, "\n-----Conformance checks completed----- ");
        fclose($opfile);
    }
}

function crossValidation_DVB_Representations($dom, $opfile, $xml_r, $xml_d, $i, $r, $d){
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

function crossValidation_HbbTV_Representations($dom, $opfile, $xml_r, $xml_d, $i, $r, $d){
    $adapt = $dom->getElementsByTagName('AdaptationSet')->item($i);
    $rep_r = $adapt->getElementsByTagName('Representation')->item($r);
    $rep_d = $adapt->getElementsByTagName('Representation')->item($d);
    
    ## Section E.3.2 checks on Adaptation Sets
    // Second bullet on same trackID
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
        fwrite($opfile, "###'HbbTV check violated: Section E.3.2- All ISO BMFF Representations SHALL have the same track_ID in the track header box and track fragment header box', not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    
    // Third bullet on initialization segment identicalness
    $stsd_r = $xml_r->getElementsByTagName('stsd')->item(0);
    $stsd_d = $xml_d->getElementsByTagName('stsd')->item(0);
    
    if(!nodes_equal($stsd_r, $stsd_d))
        fwrite($opfile, "###'HbbTV check violated: Section E.3.2- Initialization Segment SHALL be common for all Representations', not equal in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    ##
    
    $hdlr_r = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_r = $hdlr_r->getAttribute('handler_type');
    $sdType_r = $xml_r->getElementsByTagName($hdlr_type_r.'_sampledescription')->item(0)->getAttribute('sdType');
    
    $hdlr_d = $xml_r->getElementsByTagName('hdlr')->item(0);
    $hdlr_type_d = $hdlr_d->getAttribute('handler_type');
    $sdType_d = $xml_d->getElementsByTagName($hdlr_type_d.'_sampledescription')->item(0)->getAttribute('sdType');
    
    ## Highlight HEVC and AVC for different representations in the same Adaptation Set
    if($hdlr_type_r == 'vide' && $hdlr_type_d == 'vide'){
        if((($sdType_r == 'hev1' || $sdType_r == 'hvc1') && strpos($sdType_d, 'avc')) || (($sdType_d == 'hev1' || $sdType_d == 'hvc1') && strpos($sdType_r, 'avc')))
            fwrite($opfile, "Warning for HbbTV check: 'Terminals cannot switch between HEVC and AVC video Represntations present in the same Adaptation Set', found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
    }
    ##
    
    ## Highlight 5.1 Audio and 2.0 Audio
    if($hdlr_type_r == 'soun' && $hdlr_type_d == 'soun'){
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
                fwrite($opfile, "Warning for HbbTV check: '5.1 Audio and 2.0 Audio SHOULD NOT be present within the same Adaptation Set for the presence of consistent Representations within an Adaptation Set ', found in Adaptation Set " . ($i+1) . ": Representation " . ($r+1) . " and Representation " . ($d+1) . ".\n");
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

function common_validation($dom,$hbbtv,$dvb, $sizearray){
    global $locate, $count1, $count2;
    
    if(!($opfile = fopen($locate."/Adapt".$count1."rep".$count2."log.txt", 'a'))){
        echo "Error opening/creating HbbTV/DVB codec validation file: "."/Adapt".$count1."rep".$count2."log.txt";
        return;
    }
    
    $xml_rep = xmlFileLoad($locate.'/Adapt'.$count1.'/Adapt'.$count1.'rep'.$count2.'.xml');

    if($dvb){
        common_validation_DVB($opfile, $dom, $xml_rep, $count1, $count2, $sizearray);
    }
    if($hbbtv){
        common_validation_HbbTV($opfile, $dom, $xml_rep, $count1, $count2);
    }
     seg_timing_common($opfile,$xml_rep);

    $checks = segmentToPeriodDurationCheck($xml_rep);
    if(!$checks[0]){
        fwrite($opfile, "###'HbbTV/DVB check violated: The accumulated duration of the segments [".$checks[1]. "seconds] in the representation does not match the period duration[".$checks[2]."seconds].\n'");
    }

}

function common_validation_DVB($opfile, $dom, $xml_rep, $adapt_count, $rep_count, $sizearray){
    global $profiles, $locate;
    
    $adapt = $dom->getElementsByTagName('AdaptationSet')->item($adapt_count);
    $rep = $adapt->getElementsByTagName('Representation')->item($rep_count);
    
    ## Report on any resolutions used that are not in the tables of resoultions in 10.3 of the DVB DASH specification
    $res_result = resolutionCheck($opfile, $adapt, $rep);
    if($res_result[0] == false)
        fwrite ($opfile, "Information on DVB codec conformance: Resolution value \"" . $res_result[1] . 'x' . $res_result[2] . "\" is not in the table of resolutions in 10.3 of the DVB DASH specification.\n");
    ##
    
    ## Check on the support of the provided codec
    // MPD part
    $codecs = $adapt->getAttribute('codecs');
    if($codecs == ''){
        $codecs = $rep->getAttribute('codecs');
    }
    
    if($codecs != ''){
        $codecs_arr = explode(',', $codecs);
        
        $str_info = '';
        foreach($codecs_arr as $codec){
            if(strpos($codec, 'avc') === FALSE && strpos($codec, 'hev1') === FALSE && strpos($codec, 'hvc1') === FALSE && 
                strpos($codec, 'mp4a') === FALSE && strpos($codec, 'ec-3') === FALSE && strpos($codec, 'ac-4') === FALSE &&
                strpos($codec, 'dtsc') === FALSE && strpos($codec, 'dtsh') === FALSE && strpos($codec, 'dtse') === FALSE && strpos($codec, 'dtsi') === FALSE &&
                strpos($codec, 'stpp') === FALSE){
                
                $str_info .= "$codec "; 
            }
        }
        
        if($str_info != '')
            fwrite($opfile, "###'DVB check violated: @codecs in the MPD is not supported by the specification', found $str_info.\n");
    }
    
    // Segment part
    $hdlr_type = $xml_rep->getElementsByTagName('hdlr')->item(0)->getAttribute('handler_type');
    $sdType = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('sdType');
    
    if(strpos($sdType, 'avc') === FALSE && strpos($sdType, 'hev1') === FALSE && strpos($sdType, 'hvc1') === FALSE && 
       strpos($sdType, 'mp4a') === FALSE && strpos($sdType, 'ec-3') === FALSE && strpos($sdType, 'ac-4') === FALSE &&
       strpos($sdType, 'dtsc') === FALSE && strpos($sdType, 'dtsh') === FALSE && strpos($sdType, 'dtse') === FALSE && strpos($sdType, 'dtsi') === FALSE &&
       strpos($sdType, 'stpp') === FALSE)
        fwrite($opfile, "###'DVB check violated: codec in the Segment is not supported by the specification', found $sdType.\n");
    
    if(strpos($sdType, 'avc') !== FALSE){
        $nal_units = $xml_rep->getElementsByTagName('NALUnit');
        foreach($nal_units as $nal_unit){
            if($nal_unit->getAttribute('nal_type') == '0x07'){
                if($nal_unit->getAttribute('profile_idc') != 100)
                    fwrite($opfile, "###'DVB check violated: profile used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('profile_idc') . ".\n");
            
                $level_idc = $nal_unit->getElementsByTagName('comment')->item(0)->getAttribute('level_idc');
                if($level_idc != 30 && $level_idc != 31 && $level_idc != 32 && $level_idc != 40)
                    fwrite($opfile, "###'DVB check violated: level used for the codec in Segment is not supported by the specification', found $level_idc.\n");
            }
        }
    }
    elseif(strpos($sdType, 'hev1') !== FALSE || strpos($sdType, 'hvc1') !== FALSE){
        $width = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('width');
        $height = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('height');
        $nal_units = $xml_rep->getElementsByTagName('NALUnit');
        foreach($nal_units as $nal_unit){
            if($nal_unit->getAttribute('nalUnitType') == '33'){
                if($nal_unit->getAttribute('gen_tier_flag') != '0')
                    fwrite($opfile, "###'DVB check violated: tier used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('gen_tier_flag') . ".\n");
                if($nal_unit->getAttribute('bit_depth_luma_minus8') != 0 && $nal_unit->getAttribute('bit_depth_luma_minus8') != 2)
                    fwrite($opfile, "###'DVB check violated: bit depth used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('bit_depth_luma_minus8') . ".\n");
                
                if((int)$width <= 1920 && (int)$height <= 1080){
                    if($nal_unit->getAttribute('gen_profile_idc') != '1' && $nal_unit->getAttribute('gen_profile_idc') != '2')
                        fwrite($opfile, "###'DVB check violated: profile used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('gen_profile_idc') . ".\n");
                    if((int)($nal_unit->getAttribute('sps_max_sub_layers_minus1')) == 0 && (int)($nal_unit->getAttribute('gen_level_idc')) > 123)
                        fwrite($opfile, "###'DVB check violated: level used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('gen_level_idc') . ".\n");
                }
                elseif((int)$width > 1920 && (int)$height > 1080){
                    if($nal_unit->getAttribute('gen_profile_idc') != '2')
                        fwrite($opfile, "###'DVB check violated: profile used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('gen_profile_idc') . ".\n");
                    if((int)($nal_unit->getAttribute('sps_max_sub_layers_minus1')) == 0 && (int)($nal_unit->getAttribute('gen_level_idc')) > 153)
                        fwrite($opfile, "###'DVB check violated: level used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('gen_level_idc') . ".\n");
                }
            }
        }
    }
    ##
    
    ## Segment checks
    $moof_boxes = $xml_rep->getElementsByTagName('moof');
    // Section 4.3 on on-demand profile periods containing sidx boxes
    if(strpos($profiles, 'urn:mpeg:dash:profile:isoff-on-demand:2011') !== FALSE){
        if($xml_rep->getElementsByTagName('sidx')->length != 1)
            fwrite($opfile, "###'DVB check violated: Section 4.3- (For On Demand profile) The segment SHALL contain only one single Segment Index box ('sidx) for the entire segment', found more than one sidx boxes.\n");
        
        if(count(glob($locate.'/Adapt'.$adapt_count.'rep'.$rep_count.'/*mp4')) < 1)
            fwrite($opfile, "###'DVB check violated: Section 4.3- (For On Demand profile) Each Representation SHALL have only one Segment', found more.\n");
    }
    
    // Section 4.3 on traf box count in moof boxes
    foreach($moof_boxes as $moof_box){
        if($moof_box->getElementsByTagName('traf')->length != 1)
            fwrite($opfile, "###'DVB check violated: Section 4.3- The movie fragment box ('moof') SHALL contain only one track fragment box ('traf')', found more than one.\n");
    }
    
    // Section 4.5 on segment and subsegment durations
    $sidx_boxes = $xml_rep->getElementsByTagName('sidx');
    $subsegment_signaling = array();
    if($sidx_boxes->length != 0){
        foreach($sidx_boxes as $sidx_box){
            $subsegment_signaling[] = (int)($sidx_box->getAttribute('referenceCount'));
        }
    }
    
    $timescale=$xml_rep->getElementsByTagName('mdhd')->item(0)->getAttribute('timescale');
    $num_moofs=$moof_boxes->length;
    $sidx_index = 0;
    $cum_subsegDur=0;
    for($j=0;$j<$num_moofs-1;$j++){
        $cummulatedSampleDuration=$xml_rep->getElementsByTagName('trun')->item($j)->getAttribute('cummulatedSampleDuration');
        $segDur=$cummulatedSampleDuration/$timescale;
        
        if(empty($subsegment_signaling) || (!empty($subsegment_signaling) && sizeof(array_unique($subsegment_signaling)) == 1 && in_array(0, $subsegment_signaling))){
            if($hdlr_type =='vide' && $segDur>15)
                fwrite($opfile, "###'DVB check violated Section 4.5: Where subsegments are not signalled, each video segment SHALL have a duration of not more than 15 seconds', segment ".($j+1)." found with duration ".$segDur." \n");
            if($hdlr_type =='soun' && $segDur>15)
                fwrite($opfile, "###'DVB check violated Section 4.5: Where subsegments are not signalled, each audio segment SHALL have a duration of not more than 15 seconds', segment ".($j+1)." found with duration ".$segDur." \n");
            
            if($segDur <1)
                fwrite($opfile, "###'DVB check violated Section 4.5: Segment duration SHALL be at least 1 second except for the last segment of a Period', segment ".($j+1)." found with duration ".$segDur." \n");
        }
        elseif(!empty($subsegment_signaling) && !in_array(0, $subsegment_signaling)){
            $ref_count = $subsegment_signaling[$sidx_index];
            $cum_subsegDur += $segDur;
            if($hdlr_type =='vide' && $segDur>15)
                fwrite($opfile, "###'DVB check violated Section 4.5: Each video subsegment SHALL have a duration of not more than 15 seconds', subsegment ".($j+1)." found with duration ".$segDur." \n");
            if($hdlr_type =='soun' && $segDur>15)
                fwrite($opfile, "###'DVB check violated Section 4.5: Each audio subsegment SHALL have a duration of not more than 15 seconds', subsegment ".($j+1)." found with duration ".$segDur." \n");
            
            $subsegment_signaling[$sidx_index] = $ref_count - 1;
            if($subsegment_signaling[$sidx_index] == 0){
                if($cum_subsegDur < 1)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Segment duration SHALL be at least 1 second except for the last segment of a Period', segment ".($j+1)." found with duration ".$segDur." \n");
                
                $sidx_index++;
                $cum_subsegDur = 0;
            }
            
            // Section 5.1.2 on AVC content's SAP type
            if($hdlr_type == 'vide' && strpos($sdType, 'avc') !== FALSE){
                $subseg = $sidx_box->getElementsByTagName('subsegment')->item(0);
                if($subseg != NULL && $subseg->getAttribute('starts_with_SAP') == '1'){
                    $sap_type = $subseg->getAttribute('SAP_type');
                    if($sap_type != '1' && $sap_type != '2')
                        fwrite($opfile, "###'DVB check violated: Section 5.1.2- Segments SHALL start with SAP types of 1 or 2', found $sap_type.\n");
                }
            }
            //
        }
        else{
            $ref_count = $subsegment_signaling[$sidx_index];
            if($ref_count == 0){
                if($hdlr_type =='vide' && $segDur>15)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Where subsegments are not signalled, each video segment SHALL have a duration of not more than 15 seconds', segment ".($j+1)." found with duration ".$segDur." \n");
                if($hdlr_type =='soun' && $segDur>15)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Where subsegments are not signalled, each audio segment SHALL have a duration of not more than 15 seconds', segment ".($j+1)." found with duration ".$segDur." \n");
                
                if($segDur <1)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Segment duration SHALL be at least 1 second except for the last segment of a Period', segment ".($j+1)." found with duration ".$segDur." \n");
                
                $sidx_index++;
            }
            else{
                $subsegment_signaling[$sidx_index] = $ref_count - 1;
                $cum_subsegDur += $segDur;
                if($hdlr_type =='vide' && $segDur>15)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Each video subsegment SHALL have a duration of not more than 15 seconds', subsegment ".($j+1)." found with duration ".$segDur." \n");
                if($hdlr_type =='soun' && $segDur>15)
                    fwrite($opfile, "###'DVB check violated Section 4.5: Each audio subsegment SHALL have a duration of not more than 15 seconds', subsegment ".($j+1)." found with duration ".$segDur." \n");
                
                if($subsegment_signaling[$sidx_index] == 0){
                    $sidx_index++;
                    if($cum_subsegDur < 1)
                        fwrite($opfile, "###'DVB check violated Section 4.5: Segment duration SHALL be at least 1 second except for the last segment of a Period', segment ".($j+1)." found with duration ".$segDur." \n");
                    
                    $cum_subsegDur = 0;
                }
                
                // Section 5.1.2 on AVC content's SAP type
                if($hdlr_type == 'vide' && strpos($sdType, 'avc') !== FALSE){
                $subseg = $sidx_box->getElementsByTagName('subsegment')->item(0);
                    if($subseg != NULL && $subseg->getAttribute('starts_with_SAP') == '1'){
                        $sap_type = $subseg->getAttribute('SAP_type');
                        if($sap_type != '1' && $sap_type != '2')
                            fwrite($opfile, "###'DVB check violated: Section 5.1.2- Segments SHALL start with SAP types of 1 or 2', found $sap_type.\n");
                    }
                }
                //
            }
        }
        
        // Section 6.2 on HE_AACv2 and 6.5 on MPEG Surround audio content's SAP type
        if($hdlr_type == 'soun' && strpos($sdType, 'mp4a') !== FALSE){
            $subsegments = $sidx_box->getElementsByTagName('subsegment');
            if($subsegments->length != 0){
                foreach($subsegments as $subsegment){
                    if($subsegment->getAttribute('starts_with_SAP') == '1'){
                        $sap_type = $subsegment->getAttribute('SAP_type');
                        if($sap_type != '1')
                            fwrite($opfile, "###'DVB check violated: Section 6.2/6.5- The content preparation SHALL ensure that each (Sub)Segment starts with a SAP type 1', found $sap_type in Adaptation Set " . ($adapt_count+1) . " Representation " . ($rep_count+1) . ".\n");
                    }
                }
            }
        }
        //
    }
    ##
    
    // Section 5.1.2 on AVC content's sample entry type
    if($hdlr_type == 'vide' && strpos($sdType, 'avc') !== FALSE){
        if($sdType != 'avc3' && $sdType != 'avc4')
            fwrite($opfile, "Warning for DVB check: Section 5.1.2- 'Content SHOULD be offered using Inband storage for SPS/PPS i.e. sample entries 'avc3' and 'avc4'.', found $sdType.\n");
    }
    
    // Section 4.5 on subtitle segment sizes
    if($hdlr_type == 'subt'){
        $segsize_info = '';
        foreach($sizearray as $segsize){
            if($segsize > 512*1024)
                $segsize_info .= 'large ';
        }
        if($segsize_info != '')
            fwrite($opfile, "###'DVB check violated: Section 4.5- Subtitle segments SHALL have a maximum segment size of 512KB', found larger segment size.\n");
    }
}

function common_validation_HbbTV($opfile, $dom, $xml_rep, $adapt_count, $rep_count){
    $adapt = $dom->getElementsByTagName('AdaptationSet')->item($adapt_count);
    $rep = $adapt->getElementsByTagName('Representation')->item($rep_count);
    
    ## Check on the support of the provided codec
    // MPD part
    $codecs = $adapt->getAttribute('codecs');
    if($codecs == ''){
        $codecs = $rep->getAttribute('codecs');
    }
    
    if($codecs != ''){
        $codecs_arr = explode(',', $codecs);
        
        $str_info = '';
        foreach($codecs_arr as $codec){
            if(strpos($codec, 'avc') === FALSE &&
                strpos($codec, 'mp4a') === FALSE && strpos($codec, 'ec-3')){
                
                $str_info .= "$codec "; 
            }
        }
        
        if($str_info != '')
            fwrite($opfile, "###'HbbTV check violated: @codecs in the MPD is not supported by the specification', found $str_info.\n");
    }
    
    // Segment part
    $hdlr_type = $xml_rep->getElementsByTagName('hdlr')->item(0)->getAttribute('handler_type');
    $sdType = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('sdType');
    
    if(strpos($sdType, 'avc') === FALSE && 
       strpos($sdType, 'mp4a') === FALSE && strpos($sdType, 'ec-3') === FALSE)
        fwrite($opfile, "###'HbbTV check violated: codec in Segment is not supported by the specification', found $sdType.\n");
    
    if(strpos($sdType, 'avc') !== FALSE){
        $width = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('width');
        $height = $xml_rep->getElementsByTagName("$hdlr_type".'_sampledescription')->item(0)->getAttribute('height');
        $nal_units = $xml_rep->getElementsByTagName('NALUnit');
        foreach($nal_units as $nal_unit){
            if($nal_unit->getAttribute('nal_type') == '0x07'){
                if((int)$width <= 720 && (int)$height <= 576){
                    if($nal_unit->getAttribute('profile_idc') != 77 && $nal_unit->getAttribute('profile_idc') != 100)
                        fwrite($opfile, "###'HbbTV check violated: profile used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('profile_idc') . ".\n");
                    
                    $level_idc = $nal_unit->getElementsByTagName('comment')->item(0)->getAttribute('level_idc');
                    if($level_idc != 30)
                        fwrite($opfile, "###'HbbTV check violated: level used for the codec in Segment is not supported by the specification', found $level_idc.\n");
                }
                elseif((int)$width >= 720 && (int)$height >= 640){
                    if($nal_unit->getAttribute('profile_idc') != 100)
                        fwrite($opfile, "###'HbbTV check violated: profile used for the codec in Segment is not supported by the specification', found " . $nal_unit->getAttribute('profile_idc') . ".\n");
                    
                    $level_idc = $nal_unit->getElementsByTagName('comment')->item(0)->getAttribute('level_idc');
                    if($level_idc != 30 && $level_idc != 31 && $level_idc != 32 && $level_idc != 40)
                        fwrite($opfile, "###'HbbTV check violated: level used for the codec in Segment is not supported by the specification', found $level_idc.\n");
                }
            }
        }
    }
    ##
    ##Segment checks.
    $stsd = $xml_rep->getElementsByTagName('stsd')->item(0);
    $vide_sample=$stsd->getElementsByTagName('vide_sampledescription');
    $soun_sample=$stsd->getElementsByTagName('soun_sampledescription');
    if($vide_sample->length>0 && $soun_sample->length>0)
        fwrite($opfile, "###'HbbTV check violated Section E.2.3: Each Representation shall contain only one media component', found both video and audio samples\n");

    if($hdlr_type =='vide')
    {
        $avcC = $xml_rep->getElementsByTagName('avcC');
        if($avcC->length>0)
        {
            $nals=$xml_rep->getElementsByTagName('NALUnit');
            foreach($nals as $nal_unit)
            {
                if($nal_unit->getAttribute('nal_type') =='0x07')
                    $sps_found=1;
                if($nal_unit->getAttribute('nal_type') =='0x08')
                    $pps_found=1;
            }
            if($sps_found!=1)
                fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for AVC video, Sequence parameter set not found\n");
            if($pps_found!=1)
                fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for AVC video, Picture parameter set not found \n");

        }
        else
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for video, AVC decoder config record not found \n");

    }
    else if($hdlr_type =='soun'){
        $soun_sample=$xml_rep->getElementsByTagName('soun_sampledescription');
        $sdType=$soun_sample->item(0)->getAttribute('sdType');
        $samplingRate=$soun_sample->item(0)->getAttribute('sampleRate');    
        $xml_audioDec=$xml_rep->getElementsByTagName('DecoderSpecificInfo');
        if($xml_audioDec->length>0)
           $channelConfig=$xml_audioDec->item(0)->getAttribute('channelConfig');
        if($sdType==NULL  )
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for audio, sample description type not found \n");
        if($samplingRate==NULL)
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for audio, sampling rate not found \n");
        if($channelConfig==NULL)
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: All info necessary to decode any Segment shall be provided in Initialization Segment', for audio, channel config in decoder specific info not found \n");

    }
    
    $mdhd=$xml_rep->getElementsByTagName('mdhd')->item(0);
    $timescale=$mdhd->getAttribute('timescale');
    $num_moofs=$xml_rep->getElementsByTagName('moof')->length;
    $totalSegmentDuration = 0;
    for($j=0;$j<$num_moofs-1;$j++)
    {
        $trun=$xml_rep->getElementsByTagName('trun')->item($j);
        $cummulatedSampleDuration=$trun->getAttribute('cummulatedSampleDuration');
        $segDur=$cummulatedSampleDuration/$timescale;
        if($segDur <1)
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: Segments shall be at least 1s long except last segment of Period', segment ".($j+1)." found with duration ".$segDur." \n");
        if($hdlr_type =='vide' && $segDur>15)
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: Each video segment shall have a duration of not more than 15s', segment ".($j+1)." found with duration ".$segDur." \n");
        if($hdlr_type =='soun' && $segDur>15)
            fwrite($opfile, "###'HbbTV check violated Section E.2.3: Each audio segment shall have a duration of not more than 15s', segment ".($j+1)." found with duration ".$segDur." \n");
        
    }   
}

function segmentToPeriodDurationCheck($xml_rep) {
    global $Periodduration;
    $Pd = timeparsing($Periodduration);
    $mdhd=$xml_rep->getElementsByTagName('mdhd')->item(0);
    $timescale=$mdhd->getAttribute('timescale');
    $num_moofs=$xml_rep->getElementsByTagName('moof')->length;
    $totalSegmentDuration = 0;
    for ( $j = 0; $j <= $num_moofs - 1 ; $j++ )
    {
        $trun = $xml_rep->getElementsByTagName('trun')->item($j);
        $cummulatedSampleDuration = $trun->getAttribute('cummulatedSampleDuration');
        $segDur = ( $cummulatedSampleDuration * 1.00 ) / $timescale;      
        $totalSegmentDuration += $segDur;
    }
    
    return [$totalSegmentDuration==$Pd, $totalSegmentDuration, $Pd];
}

// Report on any resolutions used that are not in the tables of resoultions in 10.3 of the DVB DASH specification
function resolutionCheck($opfile, $adapt, $rep){
    $conformant = true;
    
    $progressive_width  = array('1920', '1600', '1280', '1024', '960', '852', '768', '720', '704', '640', '512', '480', '384', '320', '192', '3840', '3200', '2560');
    $progressive_height = array('1080', '900',  '720',  '576',  '540', '480', '432', '404', '396', '360', '288', '270', '216', '180', '108', '2160', '1800', '1440');
    
    $interlaced_width  = array('1920', '704', '544', '352');
    $interlaced_height = array('1080', '576', '576', '288');
    
    $scanType = $adapt->getAttribute('scanType');
    if($scanType == ''){
        $scanType = $rep->getAttribute('scanType');
        
        if($scanType == '')
            $scanType = 'progressive';
    }
    
    $width = $adapt->getAttribute('width');
    $height = $adapt->getAttribute('height');
    if($width == '' && $height == ''){
        $width = $rep->getAttribute('width');
        $height = $rep->getAttribute('height');
        
        if($width != '' && $height != ''){
            if($scanType == 'progressive'){
                $ind1 = array_search($width, $progressive_width);
                if($ind1 !== FALSE){
                    if($height != $progressive_height[$ind1])
                        $conformant = false;
                }
            }
            elseif($scanType == 'interlaced'){
                $ind1 = array_search($width, $interlaced_width);
                if($ind1 !== FALSE){
                    if($height != $interlaced_height[$ind1])
                        $conformant = false;
                }
            }
        }
    }
    
    return array($conformant, $width, $height);
}

function float2int($value) {
    return value | 0;
}
function init_seg_commonCheck($files,$opfile)
{
    $rep_count=count($files);
    fwrite($opfile, "Info: There are ".$rep_count." Representation in the AdaptationSet with \n");
    for($i=0;$i<$rep_count;$i++)
    {
        $xml = xmlFileLoad($files[$i]);
        $avcC_count=$xml->getElementsByTagName('avcC')->length;
        fwrite($opfile, ", ".$avcC_count." 'avcC' in Representation ".($i+1)." \n");
    }

}

function seg_timing_common($opfile,$xml_rep)
{      
    $xml_num_moofs=$xml_rep->getElementsByTagName('moof')->length;
    $xml_trun=$xml_rep->getElementsByTagName('trun');
    $xml_tfdt=$xml_rep->getElementsByTagName('tfdt');
    for($j=1;$j<$xml_num_moofs;$j++){

        $cummulatedSampleDurFragPrev=$xml_trun->item($j-1)->getAttribute('cummulatedSampleDuration');
        $decodeTimeFragPrev=$xml_tfdt->item($j-1)->getAttribute('baseMediaDecodeTime');
        $decodeTimeFragCurr=$xml_tfdt->item($j)->getAttribute('baseMediaDecodeTime');

        if($decodeTimeFragCurr!=$decodeTimeFragPrev+$cummulatedSampleDurFragPrev){
            fprintf($opfile, "###'HbbTV/DVB check violated: A gap in the timing within the segments of the Representation found at segment number ".($j+1)."\n");
        }
    }

}