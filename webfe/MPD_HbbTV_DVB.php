<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$period_count = 0;
$adapt_video_count = 0;
$adapt_audio_count = 0;
$main_audio_found = false;
$main_video_found = false;

function HbbTV_DVB_mpdvalidator($dom, $hbbtv, $dvb) {
    global $locate, $string_info;
    
    $mpdreport = fopen($locate . '/mpdreport.txt', 'a+b');
    fwrite($mpdreport, "HbbTV-DVB Validation \n");
    fwrite($mpdreport, "===========================\n\n");


    if($dvb){
        DVB_mpdvalidator($dom, $mpdreport);
    }
    
    if($hbbtv){
        HbbTV_mpdvalidator($dom, $mpdreport);
    }
    
    fclose($mpdreport);
    $temp_string = str_replace(array('$Template$'), array("mpdreport"), $string_info);
    file_put_contents($locate . '/mpdreport.html', $temp_string);
    
    //Return 'warning' or 'error' to the mpdprocessing part.
    $returnValue="true";
    $mpdreportText=file_get_contents($locate . '/mpdreport.txt');
    if(strpos($mpdreportText, '###')!=FALSE)
            $returnValue="error";
    elseif(strpos($mpdreportText, 'Warning')!=FALSE)
             $returnValue="warning";
    
    return $returnValue;
}

function DVB_mpdvalidator($dom, $mpdreport){
    global $adapt_video_count, $adapt_audio_count, $main_audio_found, $period_count;
    
    $mpd_string = $dom->saveXML();
    $mpd_bytes = strlen($mpd_string);
    if($mpd_bytes > 256*1024){
        fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD size after xlink resolution SHALL NOT exceed 256 Kbytes', found " . ($mpd_bytes/1024) . " Kbytes.\n");
    }
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    
    ## Information from this part is used for Section 4.1 check
    $profiles = $MPD->getAttribute('profiles');
    if(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE)
        fwrite($mpdreport, "###'DVB check violated: Section 4.1- The URN for the profile (MPEG Interoperability Point) SHALL be \"urn:dvb:dash:profile:dvb-dash:2014\"', specified profile could not be found.\n");
    ##
    
    // Periods within MPD
    $period_count = 0;
    $type = $MPD->getAttribute('type');
    $AST = $MPD->getAttribute('availabilityStartTime');
    foreach($MPD->childNodes as $node){
        
        if($type == 'dynamic' || $AST != ''){
            if($node->nodeName == 'UTCTiming'){
                $acceptedTimingURIs = array('urn:mpeg:dash:utc:ntp:2014', 'urn:mpeg:dash:utc:http-head:2014', 'urn:mpeg:dash:utc:http-xsdate:2014',
                    'urn:mpeg:dash:utc:http-iso:2014','urn:mpeg:dash:utc:http-ntp:2014');
                if(!(in_array($node->getAttribute('schemeIdUri'), $acceptedTimingURIs))){
                    fwrite($mpdreport, "Warning for DVB check: Section 4.7.2- 'If the MPD is dynamic or if the MPD@availabilityStartTime is present then the MPD SHOULD countain at least one UTCTiming element with the @schemeIdUri attribute set to one of the following: $acceptedTimingURIs ', could not be found in the provided MPD.\n");
                }
            }
        }
        
        if($node->nodeName == 'Period'){
            $period_count++;
            $adapt_video_count = 0; 
            $main_video_found = false;
            
            foreach ($node->childNodes as $child){
                if($child->nodeName == 'SegmentList')
                    fwrite($mpdreport, "###'DVB check violated: Section 4.2.2- The Period.SegmentList SHALL not be present', but found in Period $period_count.\n");
            }
            
            // Adaptation Sets within each Period
            $adapts = $node->getElementsByTagName('AdaptationSet');
            $adapts_len = $adapts->length;
            
            if($adapts_len > 16)
                fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD has a maximum of 16 adaptation sets per period', found $adapts_len in Period $period_count.\n");
            
            for($i=0; $i<$adapts_len; $i++){
                $adapt = $adapts[$i];
                $video_found = false;
                $audio_found = false;
                
                $reps = $adapt->getElementsByTagName('Representation');
                $reps_len = $reps->length;
                if($reps_len > 16)
                    fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD has a maximum of 16 representations per adaptation set', found $reps_len in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                
                $contentTemp_vid_found = false;
                $contentTemp_aud_found = false;
                foreach ($adapt->childNodes as $ch){
                    if($ch->nodeName == 'ContentComponent'){
                        if($ch->getAttribute('contentType') == 'video')
                            $contentTemp_vid_found = true;
                        if($ch->getAttribute('contentType') == 'audio')
                            $contentTemp_aud_found = true;
                    }
                    if($ch->nodeName == 'Representation'){
                        if(strpos($ch->getAttribute('mimeType'), 'video') !== FALSE)
                            $video_found = true;
                        if(strpos($ch->getAttribute('mimeType'), 'audio') !== FALSE)
                            $audio_found = true;
                    }
                }
                
                if($adapt->getAttribute('contentType') == 'video' || $contentTemp_vid_found || $video_found || strpos($adapt->getAttribute('contentType'), 'video') !== FALSE){
                    DVB_video_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found);
                    
                    if($contentTemp_aud_found){
                        DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found);
                    }
                }
                elseif($adapt->getAttribute('contentType') == 'audio' || $contentTemp_aud_found || $audio_found || strpos($adapt->getAttribute('contentType'), 'audio') !== FALSE){
                    DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found);
                    
                    if($contentTemp_vid_found){
                        DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found);
                    }
                }
                else{
                    DVB_subtitle_checks($adapt, $reps, $mpdreport, $i);
                }
                
                if($adapt_video_count > 1 && $main_video_found == false)
                    fwrite($mpdreport, "###'DVB check violated: Section 4.2.2- If a Period element contains multiple Adaptation Sets with @contentType=\"video\" then at least one Adaptation Set SHALL contain a Role element with @schemeIdUri=\"urn:mpeg:dash:role:2011\" and @value=\"main\"', could not be found in Period $period_count.\n");
            }
        }
        
        if($period_count > 64)
            fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD has a maximum of 64 periods after xlink resolution', found $period_count.\n");
    }
    
    if($adapt_audio_count > 1 && $main_audio_found == false)
        fwrite($mpdreport, "###'DVB check violated: Section 6.1.2- If there is more than one audio Adaptation Set in a DASH Presentation then at least one of them SHALL be tagged with an @value set to \"main\"', could not be found in Period $period_count.\n");
    
}

function DVB_video_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found){
    global $adapt_video_count, $main_video_found, $period_count;
    
    ## Information from this part is used for Section 4.2.2 check about multiple Adaptation Sets with video as contentType
    if($adapt->getAttribute('contentType') == 'video'){
        $adapt_video_count++;
    }
    
    if($adapt->getAttribute('contentType') == 'video'){
        foreach ($adapt->childNodes as $ch){
            if($ch->name == 'Role'){
                if($ch->getAttribute('schemeIdUri') == 'urn:mpeg:dash:role:2011' && $ch->getAttribute('value') == 'main')
                    $main_video_found = true;
            }
        }
    }
    ##
    
    $adapt_width_present = true; 
    $adapt_height_present = true; 
    $adapt_frameRate_present = true;
    if($adapt->getAttribute('width') == '')
        $adapt_width_present = false;
    if($adapt->getAttribute('height') == '')
        $adapt_height_present = false;
    if($adapt->getAttribute('frameRate') == '')
        $adapt_frameRate_present = false;
    
    $adapt_codecs = $adapt->getAttribute('codecs');
    $reps_len = $reps->length;
    $reps_codecs = array();
    $subreps_codecs = array();
    for($j=0; $j<$reps_len; $j++){
        $rep = $reps[$j];
        
        ## Information from this part is used for Section 4.4 check
        $reps_width[] = $rep->getAttribute('width');
        $reps_height[] = $rep->getAttribute('height');
        $reps_frameRate[] = $rep->getAttribute('frameRate');
        $reps_scanType[] = $rep->getAttribute('scanType');
        
        if($adapt->getAttribute('contentType') == 'video'){
            if($adapt_width_present == false && $rep->getAttribute('width') == '')
                fwrite($mpdreport, "###'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @width attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
            if($adapt_height_present == false && $rep->getAttribute('height') == '')
                fwrite($mpdreport, "###'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @height attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
            if($adapt_frameRate_present == false && $rep->getAttribute('frameRate') == '')
                fwrite($mpdreport, "###'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @frameRate attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
            if($adapt->getAttribute('sar') == '' && $rep->getAttribute('sar') == '')
                fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Representation within an Adaptation Set with @contentType=\"video\" @sar attribute SHOULD be present or inherited from the Adaptation Set', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        }
        ##
        
        $reps_codecs[] = $rep->getAttribute('codecs');
        $subreps = $rep->getElementsByTagName('SubRepresentation');
        for($k=0; $k<$subreps->length; $k++){
            $subrep = $subreps[$k];
            $subreps_codecs[] = $subrep->getAttribute('codecs');
        }
    }
    
    ## Information from this part is used for Section 5.1 AVC codecs
    if((strpos($adapt_codecs, 'avc') !== FALSE)){
        $codec_parts = array();
        $codecs = explode(',', $$adapt_codecs);
        foreach($codecs as $codec){
            if(strpos($codec, 'avc') !== FALSE){
                $codec_parts = explode('.', $codec);
                $pcl = strlen($codec_parts[1]);
                if($pcl != 6)
                    fwrite($mpdreport, "###'DVB check violated: Section 5.1.3- If (AVC video codec is) present the value of @codecs attribute SHALL be set in accordance with RFC 6381, clause 3.3', not found or not complete within Period $period_count Adaptation Set " . ($i+1) . ".\n");
            }
        }
    }
    foreach($reps_codecs as $rep_codecs){
        $codecs = explode(',', $rep_codecs);
        foreach($codecs as $codec){
            if(strpos($codec, 'avc') !== FALSE){
                $codec_parts = explode('.', $codec);
                $pcl = strlen($codec_parts[1]);
                if($pcl != 6)
                    fwrite($mpdreport, "###'DVB check violated: Section 5.1.3- If (AVC video codec is) present the value of @codecs attribute SHALL be set in accordance with RFC 6381, clause 3.3', not found or not complete within Period $period_count Adaptation Set " . ($i+1) . ".\n");
            }
        }
    }
    foreach($subreps_codecs as $subrep_codecs){
        $codecs = explode(',', $subrep_codecs);
        foreach($codecs as $codec){
            if(strpos($codec, 'avc') !== FALSE){
                $codec_parts = explode('.', $codec);
                $pcl = strlen($codec_parts[1]);
                if($pcl != 6)
                    fwrite($mpdreport, "###'DVB check violated: Section 5.1.3- If (AVC video codec is) present the value of @codecs attribute SHALL be set in accordance with RFC 6381, clause 3.3', not found or not complete within Period $period_count Adaptation Set " . ($i+1) . ".\n");
            }
        }
    }
    ##
    
    ## Information from this part is used for Section 4.4 check
    if($adapt->getAttribute('contentType') == 'video'){
        if($adapt->getAttribute('maxWidth') == '' || (array_unique($reps_width) === 1 && $adapt_width_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxWidth attribute (or @width if all Representations have the same width) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('maxHeight') == '' || (array_unique($reps_height) === 1 && $adapt_height_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxHeight attribute (or @height if all Representations have the same height) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('maxFrameRate') == '' || (array_unique($reps_frameRate) === 1 && $adapt_frameRate_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxFrameRate attribute (or @frameRate if all Representations have the same frameRate) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('par') == '')
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @par attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    
        $adapt_scanType = $adapt->getAttribute('scanType');
        if(($adapt_scanType == 'interlaced') || in_array('interlaced', $reps_scanType)){
            if(empty($reps_scanType) || array_unique($reps_scanType) !== 1 || !(in_array('interlaced', $reps_scanType)))
                fwrite($mpdreport, "###'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @scanType attribute SHALL be present if interlaced pictures are used within any Representation in the Adaptation Set', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        }
    }
    ##
}

function DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found){
    global $adapt_audio_count, $main_audio_found, $period_count;
    
    if($adapt->getAttribute('contentType') == 'audio'){
        $adapt_audio_count++;
    }
    
    $adapt_role_element_found = false;
    $rep_role_element_found = false;
    $contentComp_role_element_found = false;
    $adapt_audioChConf_element_found = false;
    $adapt_audioChConf_scheme = '';
    $adapt_mimeType = $adapt->getAttribute('mimeType');
    $adapt_audioSamplingRate = $adapt->getAttribute('audioSamplingRate');
    $adapt_specific_role_count = 0;
    $adapt_codecs = $adapt->getAttribute('codecs');
    
    foreach($adapt->childNodes as $ch){
        if($ch->nodeName == 'Role'){
            $adapt_role_element_found = true;
            
            if($ch->getAttribute('schemeIdUri') == 'urn:mpeg:dash:role:2011'){
                $adapt_specific_role_count++;
                $role_values[] = $ch->getAttribute('value');
            }
            if($adapt->getAttribute('contentType') == 'audio' && $ch->getAttribute('value') == 'main'){
                $main_audio_found = true;
                $main_audios[] = $adapt;
            }
        }
        if($ch->nodeName == 'Accessibility'){
            if($ch->getAttribute('schemeIdUri') == 'urn:tva:metadata:cs:AudioPurposeCS:2007'){
                $accessibility_roles[] = $ch->getAttribute('value');
            }
        }
        if($ch->nodeName == 'AudioChannelConfiguration'){
            $adapt_audioChConf_element_found = true;
            $adapt_audioChConf_scheme[] = $ch->getAttribute('schemeIdUri');
        }
        if($contentTemp_aud_found && $ch->nodeName == 'ContentComponent'){
            if($ch->getAttribute('contentType') == 'audio'){
                foreach($ch->childNodes as $c){
                    if($c->nodeName == 'Role'){
                        $contentComp_role_element_found = true;
                    }
                }
            }
        }
    }
    
    ## Information from this part is for Section 6.1: distinguishing Adaptation Sets
    if($adapt->getAttribute('contentType') == 'audio' && $adapt_specific_role_count == 0)
        fwrite($mpdreport, "###'DVB check violated: Section 6.1.2- Every audio Adaptation Set SHALL include at least one Role Element using the scheme \"urn:mpeg:dash:role:2011\" as defined in ISO/IEC 23009-1', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    ##
    
    ## Information from this part is for Section 6.3:Dolby and 6.4:DTS
    if(strpos($adapt_codecs, 'ec-3') || strpos($adapt_codecs, 'ac-4')){
        if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme))
            fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
    }
    if(strpos($adapt_codecs, 'dtsc') || strpos($adapt_codecs, 'dtsh') || strpos($adapt_codecs, 'dtse') || strpos($adapt_codecs, 'dtsi')){
        if(!in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $adapt_audioChConf_scheme))
            fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
    }
    ##
    
    $reps_len = $reps->length;
    $rep_audioChConf_scheme = array();
    $subrep_audioChConf_scheme = array();
    $dependencyIds = array();
    for($j=0; $j<$reps_len; $j++){
        $rep = $reps[$j];
        $rep_role_element_found = false;
        $rep_audioChConf_element_found = false;
        $rep_codecs = $rep->getAttribute('codecs');
        $dependencyIds[] = $rep->getAttribute('dependencyId');
        
        $ind = 0;
        foreach ($rep->childNodes as $ch){
            if($ch->nodeName == 'Role')
                $rep_role_element_found = true;
            if($ch->nodeName == 'AudioChannelConfiguration'){
                $rep_audioChConf_element_found = true;
                $rep_audioChConf_scheme[] = $ch->getAttribute('schemeIdUri');
            }
            if($ch->nodeName == 'SubRepresentation'){
                $ind++;
                $subrep_codecs = $ch->getAttribute('codecs');
                foreach($ch->childNodes as $c){
                    if($c->nodeName == 'AudioChannelConfiguration'){
                        $subrep_audioChConf_scheme[] = $c->getAttribute('schemeIdUri');
                    }
                }
            }
            
            ##Information from this part is for Section 6.3:Dolby and 6.4:DTS
            if(strpos($subrep_codecs, 'ec-3') || strpos($subrep_codecs, 'ac-4')){
                if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme))
                    fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
            }
            if(strpos($subrep_codecs, 'dtsc') || strpos($subrep_codecs, 'dtsc') || strpos($subrep_codecs, 'dtsc') || strpos($subrep_codecs, 'dtsc')){
                if(!in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $subrep_audioChConf_scheme))
                    fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
            }
            ##
        }
        
        ##Information from this part is for Section 6.3:Dolby and 6.4:DTS
        if(strpos($rep_codecs, 'ec-3') || strpos($rep_codecs, 'ac-4')){
            if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme))
                fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
        }
        if(strpos($rep_codecs, 'dtsc') || strpos($rep_codecs, 'dtsh') || strpos($rep_codecs, 'dtse') || strpos($rep_codecs, 'dtsi')){
            if(!in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $adapt_audioChConf_scheme))
                fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
        }
        ##
        
        ## Information from this part is for Section 6.1 Table 3
        if($adapt_role_element_found == false && $contentComp_role_element_found == false && $rep_role_element_found == false)
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', Role element could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        if($adapt_audioChConf_element_found == false && $rep_audioChConf_element_found == false)
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', AudioChannelConfiguration element could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        if($adapt_mimeType == '' && $rep->getAttribute('mimeType') == '')
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', mimeType attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        if($adapt_codecs == '' && $rep_codecs == '')
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', codecs attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        if($adapt_audioSamplingRate == '' && $rep->getAttribute('audioSamplingRate') == '')
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', audioSamplingRate attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        ##
    }
    
    ## Information from this part is for Section 6.1: Receiver Mix AD 
    if(in_array('commentary', $role_values) && in_array('1', $accessibility_roles)){
        if(empty($dependencyIds))
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.2- For receiver mixed Audio Description the associated audio stream SHALL use the @dependencyId attribute to indicate the dependency to the related Adaptation Set's Representations', not found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    }
    ##
}

function DVB_subtitle_checks($adapt, $reps, $mpdreport, $i){
    global $period_count;
    
    $adapt_mimeType = $adapt->getAttribute('mimeType');
    $adapt_codecs = $adapt->getAttribute('codecs');
    $adapt_type = $adapt->getAttribute('contentType');
    foreach($adapt->childNodes as $ch){
        if($ch->nodeName == 'ContentComponent'){
            $contentComp_type[] = $adapt->getAttribute('contentType');
        }
    }
    
    $reps_len = $reps->length;
    for($j=0; $j<$reps_len; $j++){
        $rep = $reps[$j];
        
        $rep_codecs = $rep->getAttribute('codecs');
        $subrep_codecs = array();
        foreach ($rep->childNodes as $ch){
            if($ch->nodeName == 'SubRepresentation')
                $subrep_codecs[] = $ch->getAttribute('codecs');
        }
        
        ## Information from this part is for Section 7.1: subtitle carriage
        if($adapt_mimeType == 'application/mp4' || $rep->getAttribute('mimeType') == 'application/mp4'){
            if(strpos($adapt_codecs, 'stpp') || strpos($rep_codecs, 'stpp') || in_array('stpp', $subrep_codecs)){
                if($adapt_type != 'text' && !in_array('text', $contentComp_type))
                    fwrite($mpdreport, "###'DVB check violated: Section 7.1.1- The @contetnType attribute indicated for subtitles SHALL be \"text\"', found as ". $adapt->getAttribute('contentType') . " in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
            
                if($adapt->getAttribute('lang') == '')
                    fwrite($mpdreport, "###'DVB check violated: Section 7.1.2- In oder to allow a Player to identify the primary purpose of a subtitle track, the language attribute SHALL be set on the Adaptation Set', not found on Adaptaion Set ". ($i+1) . ".\n");
            }
        }
        ##
    }
}

function HbbTV_mpdvalidator($dom, $mpdreport){
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    // Periods within MPD
    $period_count = 0;
    foreach($MPD->childNodes as $node){
        if($node->nodeName == 'Period'){
            $period_count++;
           
            // Adaptation Sets within each Period
            $adapts = $node->getElementsByTagName('AdaptationSet');
            //Following has error reporting code if MPD element is not part of validating profile.
            for($i=0; $i< ($adapts->length); $i++){
                $subSegAlign=$adapts->item($i)->getAttribute('subsegmentAlignment');
                if($subSegAlign == TRUE)
                    fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentAlignment' as true in AdaptationSet ".($i+1)." \n");

                
                $reps = $adapts->item($i)->getElementsByTagName('Representation');
                $startWithSAP=$adapts->item($i)->getAttribute('subsegmentStartsWithSAP');
                    if($startWithSAP == 1 || $startWithSAP ==2)
                        fwrite($mpdreport, "###:'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " \n");
                    else if ($startWithSAP==3){
                        if(!($reps->length>1))
                            fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " not containing more than one Representation \n");

                      
                    }
                for($j=0;$j<($reps->length);$j++){
                    $baseURL=$reps->item($j)->getElementsByTagName('BaseURL');
                    if($baseURL->length>0)
                        fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an element that is not part of the HbbTV profile', i.e., found 'BaseURL' element in Representation ".($j+1)." of AdaptationSet ".($i+1). ". \n");
                    if ($startWithSAP==3){
                      $currentChild=$reps->item($j);
                        $currentId= $currentChild->getAttribute('mediaStreamStructureId');
                        while($currentChild && $currentId!=NULL){
                            $currentChild=nextElementSibling($currentChild);
                            if($currentChild!==NULL){
                                $nextId=$currentChild->getAttribute('mediaStreamStructureId');
                                if($currentId==$nextId){
                                    fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " with same value of mediaStreamStructureId in more than one Representation \n");

                                }
                            }
                        }
                     }

                }
                
            }
    
        }  
    }
}
//Function to find the next Sibling. php funciton next_sibling() is not working.So using this helper function.
function nextElementSibling($node)
{
    while ($node && ($node = $node->nextSibling)) {
        if ($node instanceof DOMElement) {
            break;
        }
    }
    return $node;
}