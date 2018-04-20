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
$video_bw = array();
$audio_bw = array();
$subtitle_bw = array();

function HbbTV_DVB_mpdvalidator($dom, $hbbtv, $dvb) {
    global $locate, $string_info;
    
    $mpdreport = fopen($locate . '/mpdreport.txt', 'a+b');
    fwrite($mpdreport, "HbbTV-DVB Validation \n");
    fwrite($mpdreport, "===========================\n\n");

    ## Report on profile-specific media types' completeness
    DVB_HbbTV_profile_specific_media_types_report($dom, $mpdreport);
    
    ## Informational cross-profile check
#    if(!$dvb && $hbbtv){
        DVB_HbbTV_cross_profile_check($dom, $mpdreport);
#    }
    
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

function DVB_HbbTV_profile_specific_media_types_report($dom, $mpdreport){
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    $mpd_profiles = $MPD->getAttribute('profiles');
    
    $profiles_arr = explode(',', $mpd_profiles);
    if(sizeof($profiles_arr) > 1){
        ## Generate the profile-specific MPDs
        foreach($profiles_arr as $profile){
            
            $domDocument = new DOMDocument('1.0');
            $domElement = $domDocument->createElement('MPD');
            $domElement = $MPD->cloneNode();
    
            $domElement->setAttribute('profiles', $profile);
            $domElement = recursive_generate($MPD, $domDocument, $domElement, $profile);
            $domDocument->appendChild($domDocument->importNode($domElement, true));
            
            $profile_specific_MPDs[] = $domDocument;
        }
        
        ## Compare each profile-specific MPD with the original MPD 
        $mpd_media_types = media_types($MPD);
        $ind = 0;
        foreach($profile_specific_MPDs as $profile_specific_MPD){
            $mpd_media_types_new = media_types($profile_specific_MPD->getElementsByTagName('MPD')->item(0));
            
            $str = '';
            foreach($mpd_media_types as $mpd_media_type){
                if(!in_array($mpd_media_type, $mpd_media_types_new))
                    $str = $str . " $mpd_media_type"; 
            }
            if($str != '')
                fwrite($mpdreport, "###DVB/HbbTV Conformance violated: media type:$str is missing after the provided MPD is processed for profile: " . $profiles_arr[$ind] . ".\n");
            
            $ind++;
        }
    }
}

function recursive_generate($node, &$domDocument, &$domElement, $profile){
    foreach($node->childNodes as $child){
        if($child->nodeType == XML_ELEMENT_NODE){
            if($child->getAttribute('profiles') == '' || strpos($child->getAttribute('profiles'), $profile) !== FALSE){
                $domchild = $domDocument->createElement($child->nodeName);
                $domchild = $child->cloneNode();
                
                $domchild = recursive_generate($child, $domDocument, $domchild, $profile);
                $domElement->appendChild($domchild);
            }
        }
    }
    
    return $domElement;
}

function media_types($MPD){
    $media_types = array();
    
    $adapts = $MPD->getElementsByTagName('AdaptationSet');
    $reps = $MPD->getElementsByTagName('Representation');
    $subreps = $MPD->getElementsByTagName('SubRepresentation');
    
    if($adapts->length != 0){
        for($i=0; $i<$adapts->length; $i++){
            $adapt = $adapts->item($i);
            $adapt_contentType = $adapt->getAttribute('contentType');
            $adapt_mimeType = $adapt->getAttribute('mimeType');
            
            if($adapt_contentType == 'video' || strpos($adapt_mimeType, 'video') !== FALSE){
                $media_types[] = 'video';
            }
            if($adapt_contentType == 'audio' || strpos($adapt_mimeType, 'audio') !== FALSE){
                $media_types[] = 'audio';
            }
            if($adapt_contentType == 'text' || strpos($adapt_mimeType, 'application') !== FALSE){
                $media_types[] = 'subtitle';
            }
            
            $contentcomps = $adapt->getElementsByTagName('ContentComponent');
            foreach($contentcomps as $contentcomp){
                $contentcomp_contentType = $contentcomp->getAttribute('contentType');
                
                if($contentcomp_contentType == 'video'){
                    $media_types[] = 'video';
                }
                if($contentcomp_contentType == 'audio'){
                    $media_types[] = 'audio';
                }
                if($contentcomp_contentType == 'text'){
                    $media_types[] = 'subtitle';
                }
            }
        }
    }
    
    if($reps->length != 0){
        for($i=0; $i<$reps->length; $i++){
            $rep = $reps->item($i);
            $rep_mimeType = $rep->getAttribute('mimeType');
            
            if(strpos($rep_mimeType, 'video') !== FALSE){
                $media_types[] = 'video';
            }
            if(strpos($rep_mimeType, 'audio') !== FALSE){
                $media_types[] = 'audio';
            }
            if(strpos($rep_mimeType, 'application') !== FALSE){
                $media_types[] = 'subtitle';
            }
        }
    }
    
    if($subreps->length != 0){
        for($i=0; $i<$subreps->length; $i++){
            $subrep = $subreps->item($i);
            $subrep_mimeType = $subrep->getAttribute('mimeType');
            
            if(strpos($subrep_mimeType, 'video') !== FALSE){
                $media_types[] = 'video';
            }
            if(strpos($subrep_mimeType, 'audio') !== FALSE){
                $media_types[] = 'audio';
            }
            if(strpos($subrep_mimeType, 'application') !== FALSE){
                $media_types[] = 'subtitle';
            }
        }
    }
    
    return array_unique($media_types);
}

function DVB_HbbTV_cross_profile_check($dom, $mpdreport){
    $profiles = $dom->getElementsByTagName('MPD')->item(0)->getAttribute('profiles');
    
    $supported_profiles = array('urn:mpeg:dash:profile:isoff-on-demand:2011', 'urn:mpeg:dash:profile:isoff-live:2011', 
                                'urn:mpeg:dash:profile:isoff-main:2011', 'http://dashif.org/guidelines/dash264', 
                                'urn:dvb:dash:profile:dvb-dash:2014', 'urn:hbbtv:dash:profile:isoff-live:2012');
    
    $profiles_arr = explode(',', $profiles);
    foreach($profiles_arr as $profile){
        if(!in_array($profile, $supported_profiles))
            fwrite($mpdreport, "Information on DVB-HbbTV conformance: MPD element is scoped by the profile \"$profile\" that the tool is not validating against.\n");
    }
}

# // Previous MPD check (6) where the elements that are not used in MPD-level HbbTV profile validation  
#function DVB_HbbTV_cross_profile_check($dom, $mpdreport){
#    // All the elements here for cross-profile checks exist in DVB but not in HbbTV
#    $MPD = $dom->getElementsByTagName('MPD')->item(0);
#    
#    $BaseURLs = $MPD->getElementsByTagName('BaseURL');
#    if($BaseURLs->length != 0)
#        fwrite($mpdreport, "Information on DVB-HbbTV conformance: BaseURL element is found in the MPD. This element is scoped by DVB profile that the tool is not validating against.\n");
#    
#    if($MPD->getAttribute('type') == 'dynamic' || $MPD->getAttribute('availabilityStartTime') != ''){
#        $UTCTimings = $MPD->getElementsByTagName('UTCTiming');
#        if($UTCTimings->length != 0)
#            fwrite($mpdreport, "Information on DVB-HbbTV conformance: UTCTiming element is found in the MPD. This element is scoped by DVB profile that the tool is not validating against.\n");
#    }
#    
#    $periods = $MPD->getElementsByTagName('Period');
#    foreach($periods as $period){
#        foreach($period->childNodes as $child){
#            if($child->nodeName == 'EventStream'){
#                fwrite($mpdreport, "Information on DVB-HbbTV conformance: EventStream element is found in the MPD. This element is scoped by DVB profile that the tool is not validating against.\n");
#                
#                foreach($child->childNodes as $ch){
#                    if($ch->nodeName == 'Event')
#                        fwrite($mpdreport, "Information on DVB-HbbTV conformance: Event element is found in the MPD. This element is scoped by DVB profile that the tool is not validating against.\n");
#                }
#            }
#        }
#    }
#}

function DVB_mpdvalidator($dom, $mpdreport){
    global $adapt_video_count, $adapt_audio_count, $main_audio_found, $period_count, $audio_bw, $video_bw, $subtitle_bw, $supported_profiles;
    
    global $onRequest_array, $xlink_not_valid_array;
    
    if(!empty($onRequest_array))
    {
        $onRequest_k_v  = implode(', ', array_map(
        function ($v, $k) { return sprintf(" %s with index (starting from 0) '%s'", $v, $k); },
        $onRequest_array,array_keys($onRequest_array)));
        fwrite($mpdreport, "###'DVB check violated, MPD SHALL NOT have xlink:actuate set to onRequest', found in".$onRequest_k_v."\n"); 
    } 
    
    if(!empty($xlink_not_valid_array))
    {
        $xlink_not_valid_k_v  = implode(', ', array_map(
        function ($v, $k) { return sprintf(" %s with index (starting from 0) '%s'", $v, $k); },
        $xlink_not_valid_array,array_keys($xlink_not_valid_array)));
        fwrite($mpdreport, "###'DVB check violated, MPD invalid xlink:href', found in:".$xlink_not_valid_k_v."\n"); 
    }
    
    $mpd_string = $dom->saveXML();
    $mpd_bytes = strlen($mpd_string);
    if($mpd_bytes > 256*1024){
        fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD size after xlink resolution SHALL NOT exceed 256 Kbytes', found " . ($mpd_bytes/1024) . " Kbytes.\n");
    }
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    
    ## Warn on low values of MPD@minimumUpdatePeriod (for now the lowest possible value is assumed to be 1 second)
    if($MPD->getAttribute('minimumUpdatePeriod') != ''){
        $mup = timeparsing($MPD->getAttribute('minimumUpdatePeriod'));
        if($mup < 1)
            fwrite($mpdreport, "Warning for DVB check: 'MPD@minimumUpdatePeriod has a lower value than 1 second.\n");
    }
    ##
    
    ## Information from this part is used for Section 4.1 and 11.1 checks
    $profiles = $MPD->getAttribute('profiles');
    if(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE && strpos($profiles, 'urn:hbbtv:dash:profile:isoff-live:2012') === FALSE)
        fwrite($mpdreport, "###'DVB check violated: Section E.2.1- The MPD SHALL indicate either or both of the following profiles: \"urn:dvb:dash:profile:dvb-dash:2014\" and \"urn:hbbtv:dash:profile:isoff-live:2012\"', specified profile could not be found.\n");
    
    $profile_exists = false;
    if(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE && (strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-live:2014') === FALSE || strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === FALSE))
        fwrite($mpdreport, "Warning for DVB check: Section 11.1- 'All Representations that are intended to be decoded and presented by a DVB conformant Player SHOULD be such that they will be inferred to have an @profiles attribute that includes the profile name defined in clause 4.1 as well as either the one defined in 4.2.5 or the one defined in 4.2.8', found profiles: $profiles.\n");
    elseif(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === TRUE && (strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-live:2014') === TRUE || strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === TRUE))
        $profile_exists = true;
    ##
    
    ## Information from this part is used for Section 11.9.5: relative url warning
    $BaseURLs = $MPD->getElementsByTagName('BaseURL');
    foreach ($BaseURLs as $BaseURL){
        if(!isAbsoluteURL($BaseURL->nodeValue)){
            if($BaseURL->getAttribute('serviceLocation') != '' && $BaseURL->getAttribute('priority') != '' && $BaseURL->getAttribute('weight') != '')
                fwrite($mpdreport, "Warning for DVB check: Section 11.9.5- 'Where BaseURLs contain relative URLs, these SHOULD NOT include @serviceLocation, @priority or @weight attributes', however found in this MPD.\n");
        }
    }
    ##
    
    $cenc = $MPD->getAttribute('xmlns:cenc');
    
    // Periods within MPD
    $period_count = 0;
    $video_service = false;
    $type = $MPD->getAttribute('type');
    $AST = $MPD->getAttribute('availabilityStartTime');
    
    if($type == 'dynamic' || $AST != ''){
        $UTCTimings = $MPD->getElementsByTagName('UTCTiming');
        $acceptedTimingURIs = array('urn:mpeg:dash:utc:ntp:2014', 
                                    'urn:mpeg:dash:utc:http-head:2014', 
                                    'urn:mpeg:dash:utc:http-xsdate:2014',
                                    'urn:mpeg:dash:utc:http-iso:2014',
                                    'urn:mpeg:dash:utc:http-ntp:2014');
        $utc_info = '';
        
        if($UTCTimings->length == 0)
            fwrite($mpdreport, "Warning for DVB check: Section 4.7.2- 'If the MPD is dynamic or if the MPD@availabilityStartTime is present then the MPD SHOULD countain at least one UTCTiming element with the @schemeIdUri attribute set to one of the following: $acceptedTimingURIs ', UTCTiming element could not be found in the provided MPD.\n");
        else{
            foreach($UTCTimings as $UTCTiming){
                if(!(in_array($UTCTiming->getAttribute('schemeIdUri'), $acceptedTimingURIs)))
                    $utc_info .= 'wrong ';
            }
            
            if($utc_info != '')
                fwrite($mpdreport, "Warning for DVB check: Section 4.7.2- 'If the MPD is dynamic or if the MPD@availabilityStartTime is present then the MPD SHOULD countain at least one UTCTiming element with the @schemeIdUri attribute set to one of the following: $acceptedTimingURIs ', could not be found in the provided MPD.\n");
        }
    }
    
    foreach($MPD->childNodes as $node){
        if($node->nodeName == 'Period'){
            $period_count++;
            $adapt_video_count = 0; 
            $main_video_found = false;
                                      
            foreach ($node->childNodes as $child){
                if($child->nodeName == 'SegmentList')
                    fwrite($mpdreport, "###'DVB check violated: Section 4.2.2- The Period.SegmentList SHALL not be present', but found in Period $period_count.\n");
                
                if($child->nodeName == 'EventStream'){
                    DVB_event_checks($child, $mpdreport);
                }
                if($child->nodename == 'SegmentTemplate'){
                    if(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === TRUE)
                        fwrite($mpdreport, "###'DVB check violated: Section 4.2.6- The Period.SegmentTemplate SHALL not be present for Period elements conforming to On Demand profile', but found in Period $period_count.\n");
                }
            }
            
            // Adaptation Sets within each Period
            $adapts = $node->getElementsByTagName('AdaptationSet');
            $adapts_len = $adapts->length;
            
            if($adapts_len > 16)
                fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD has a maximum of 16 adaptation sets per period', found $adapts_len in Period $period_count.\n");
            
            for($i=0; $i<$adapts_len; $i++){
                $adapt = $adapts->item($i);
                $video_found = false;
                $audio_found = false;
                
                $adapt_profiles = $adapt->getAttribute('profiles');
                if($profile_exists && $adapt_profiles != ''){
                    if(strpos($adapt_profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE && (strpos($adapt_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-live:2014') === FALSE || strpos($adapt_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === FALSE))
                        fwrite($mpdreport, "Warning for DVB check: Section 11.1- 'All Representations that are intended to be decoded and presented by a DVB conformant Player SHOULD be such that they will be inferred to have an @profiles attribute that includes the profile name defined in clause 4.1 as well as either the one defined in 4.2.5 or the one defined in 4.2.8', found profiles: $adapt_profiles.\n");
                }
                
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
                        if($profile_exists && $adapt_profiles == ''){
                            $rep_profiles = $ch->getAttribute('profiles');
                            if($rep_profiles != ''){
                                if(strpos($rep_profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE && (strpos($rep_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-live:2014') === FALSE || strpos($rep_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === FALSE))
                                    fwrite($mpdreport, "Warning for DVB check: Section 11.1- 'All Representations that are intended to be decoded and presented by a DVB conformant Player SHOULD be such that they will be inferred to have an @profiles attribute that includes the profile name defined in clause 4.1 as well as either the one defined in 4.2.5 or the one defined in 4.2.8', found profiles: $rep_profiles.\n");
                            }
                        }
                        if(strpos($ch->getAttribute('mimeType'), 'video') !== FALSE)
                            $video_found = true;
                        if(strpos($ch->getAttribute('mimeType'), 'audio') !== FALSE)
                            $audio_found = true;
                        
                        if($profile_exists && $adapt_profiles == '' && $rep_profiles == ''){
                            foreach($ch->childNodes as $c){
                                if($c->nodeName == 'SubRepresentation'){
                                    $subrep_profiles = $c->getAttribute('profiles');
                                    if($subrep_profiles != ''){
                                        if(strpos($subrep_profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE && (strpos($subrep_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-live:2014') === FALSE || strpos($subrep_profiles, 'urn:dvb:dash:profile:dvb-dash:isoff-ext-on-demand:2014') === FALSE))
                                            fwrite($mpdreport, "Warning for DVB check: Section 11.1- 'All Representations that are intended to be decoded and presented by a DVB conformant Player SHOULD be such that they will be inferred to have an @profiles attribute that includes the profile name defined in clause 4.1 as well as either the one defined in 4.2.5 or the one defined in 4.2.8', found profiles: $subrep_profiles.\n");
                                    }
                                }
                            }
                        }
                    }
                }
                
                if($adapt->getAttribute('contentType') == 'video' || $contentTemp_vid_found || $video_found || strpos($adapt->getAttribute('mimeType'), 'video') !== FALSE){
                    $video_service = true;
                    DVB_video_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found);
                    
                    if($contentTemp_aud_found){
                        DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found);
                    }
                }
                elseif($adapt->getAttribute('contentType') == 'audio' || $contentTemp_aud_found || $audio_found || strpos($adapt->getAttribute('mimeType'), 'audio') !== FALSE){
                    DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found);
                    
                    if($contentTemp_vid_found){
                        DVB_video_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found);
                    }
                }
                else{
                    DVB_subtitle_checks($adapt, $reps, $mpdreport, $i);
                }
                
                if($adapt_video_count > 1 && $main_video_found == false)
                    fwrite($mpdreport, "###'DVB check violated: Section 4.2.2- If a Period element contains multiple Adaptation Sets with @contentType=\"video\" then at least one Adaptation Set SHALL contain a Role element with @schemeIdUri=\"urn:mpeg:dash:role:2011\" and @value=\"main\"', could not be found in Period $period_count.\n");
                
                DVB_content_protection($adapt, $reps, $mpdreport, $i, $cenc);
            }
            
            if($video_service){
                StreamBandwidthCheck($mpdreport);
            }
        }
        
        if($period_count > 64)
            fwrite($mpdreport, "###'DVB check violated: Section 4.5- The MPD has a maximum of 64 periods after xlink resolution', found $period_count.\n");
    }
    
    if($adapt_audio_count > 1 && $main_audio_found == false)
        fwrite($mpdreport, "###'DVB check violated: Section 6.1.2- If there is more than one audio Adaptation Set in a DASH Presentation then at least one of them SHALL be tagged with an @value set to \"main\"', could not be found in Period $period_count.\n");
    
}

function StreamBandwidthCheck($mpdreport){
    global $video_bw, $audio_bw, $subtitle_bw;
    
    for($v=0; $v<sizeof($video_bw); $v++){
        for($a=0; $a<sizeof($audio_bw); $a++){
            if(!empty($subtitle_bw)){
                for($s=0; $s<sizeof($subtitle_bw); $s++){
                    $total_bw = $video_bw[$v] + $subtitle_bw[$s] + $audio_bw[$a];
                    if($audio_bw[$a] > 0.2*$total_bw)
                        fwrite($mpdreport, "Warning for DVB check: Section 11.3.0- 'If the service being delivered is a video service, then audio SHOULD be 20% or less of the total stream bandwidth', exceeding stream found with bandwidth properties: video " . $video_bw[$v] . ", audio " . $audio_bw[$a] . ", subtitle " . $subtitle_bw[$s] . "\n");
                }
            }
            else{
                $total_bw = $video_bw[$v] + $audio_bw[$a];
                if($audio_bw[$a] > 0.2*$total_bw)
                    fwrite($mpdreport, "Warning for DVB check: Section 11.3.0- 'If the service being delivered is a video service, then audio SHOULD be 20% or less of the total stream bandwidth', exceeding stream found with bandwidth properties: video " . $video_bw[$v] . ", audio " . $audio_bw[$a] . "\n");
            }
        }
    }
    
    $video_bw = array();
    $audio_bw = array();
    $subtitle_bw = array();
}

function DVB_event_checks($possible_event, $mpdreport){
    global $period_count;
    if($possible_event->getAttribute('schemeIdUri') != 'urn:dvb:iptv:cpm:2014')
        fwrite($mpdreport, "###'DVB check violated: Section 9.1.2.1- The @schemeIdUri attribute (of EventStream) SHALL be set to \"urn:dvb:iptv:cpm:2014\"', not set accordingly in Period $period_count.\n");
    else{
        if($possible_event->getAttribute('value') == '1'){
            $events = $possible_event->getElementsByTagName('Event');
            foreach ($events as $event){
                if($event->getAttribute('presentationTime') == '')
                    fwrite($mpdreport, "###'DVB check violated: Section 9.1.2.1- The events associated with the @schemeIdUri attribute \"urn:dvb:iptv:cpm:2014\" and with @value attribute of \"1\", the presentationTime attribute of an MPD event SHALL be set', not set accordingly in Period $period_count.\n");
                                
                $event_value = $event->nodeValue;
                if($event_value != ''){
                    $event_str = '<doc>' . $event_value . '</doc>';
                    $event_xml = simplexml_load_string($event_str); 
                    if($event_xml === FALSE)
                        fwrite($mpdreport, "###'DVB check violated: Section 9.1.2.2- In order to carry XML structured data within the string value of an MPD Event element, the data SHALL be escaped or placed in a CDATA section in accordance with the XML specification 1.0', not done accordingly in Period $period_count.\n");
                    else{
                        foreach ($event_xml as $broadcastevent){
                            $name = $broadcastevent->getName();
                            if($name != 'BroadcastEvent')
                                fwrite($mpdreport, "###'DVB check violated: Section 9.1.2.2- The format of the event payload carrying content programme metadata SHALL be one or more TV-Anytime BroadcastEvent elements that form a valid TVAnytime XML document', not set accordingly in Period $period_count.\n");
                        }
                    }
                }
            }
        }
    }
}

function DVB_video_checks($adapt, $reps, $mpdreport, $i, $contentTemp_vid_found){
    global $adapt_video_count, $main_video_found, $period_count, $video_bw;
    
    ## Information from this part is used for Section 4.2.2 check about multiple Adaptation Sets with video as contentType
    if($adapt->getAttribute('contentType') == 'video'){
        $adapt_video_count++;
    }
    
    $ids = array();
    foreach ($adapt->childNodes as $ch){
        if($ch->name == 'Role'){
            if($adapt->getAttribute('contentType') == 'video'){
                if($ch->getAttribute('schemeIdUri') == 'urn:mpeg:dash:role:2011' && $ch->getAttribute('value') == 'main')
                    $main_video_found = true;
                }
            }
            if($ch->nodeName == 'ContentComponent'){
                if($ch->getAttribute('contentType') == 'video')
                    $ids[] = $ch->getAttribute('id');
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
        $rep = $reps->item($j);
        
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
            $subrep = $subreps->item($k);
            $subreps_codecs[] = $subrep->getAttribute('codecs');
            
            ##Information from this part is for Section 11.3.0: audio stream bandwidth percentage
            if($contentTemp_vid_found){
                if(in_array($subrep->getAttribute('contentComponent'), $ids)){
                    $video_bw[] = ($rep->getAttribute('bandwidth') != '') ? (float)($rep->getAttribute('bandwidth')) : (float)($ch->getAttribute('bandwidth'));
                }
            }
            ##
        }
        
        #Information from this part is for Section 11.3.0: audio stream bandwidth percentage
        if(!$contentTemp_vid_found){
            $video_bw[] = (float)($rep->getAttribute('bandwidth'));
        }
        ##
    }
    
    ## Information from this part is used for Section 5.1 AVC codecs
    if((strpos($adapt_codecs, 'avc') !== FALSE)){
        $codec_parts = array();
        $codecs = explode(',', $adapt_codecs);
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
        if($adapt->getAttribute('maxWidth') == '' && (array_unique($reps_width) === 1 && $adapt_width_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxWidth attribute (or @width if all Representations have the same width) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('maxHeight') == '' && (array_unique($reps_height) === 1 && $adapt_height_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxHeight attribute (or @height if all Representations have the same height) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('maxFrameRate') == '' && (array_unique($reps_frameRate) === 1 && $adapt_frameRate_present == false))
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @maxFrameRate attribute (or @frameRate if all Representations have the same frameRate) SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($adapt->getAttribute('par') == '')
            fwrite($mpdreport, "Warning for DVB check: Section 4.4- 'For any Adaptation Sets with @contentType=\"video\" @par attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    
        $adapt_scanType = $adapt->getAttribute('scanType');
        if(($adapt_scanType == 'interlaced') || in_array('interlaced', $reps_scanType)){
            if(empty($reps_scanType) || array_unique($reps_scanType) !== 1 || !(in_array('interlaced', $reps_scanType)))
                fwrite($mpdreport, "###'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @scanType attribute SHALL be present if interlaced pictures are used within any Representation in the Adaptation Set', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
        }
        
        ## Information from this part is used for Section 11.2.2 frame rate check
        $frame_rate_len = sizeof($reps_frameRate);
        for($f1=0; $f1<$frame_rate_len; $f1++){
            for($f2=$f1+1; $f2<$frame_rate_len; $f2++){
                $modulo = ($reps_frameRate[$f1] > $reps_frameRate[$f2]) ? ($reps_frameRate[$f1] % $reps_frameRate[$f2]) : ($reps_frameRate[$f2] % $reps_frameRate[$f1]);
                
                if($modulo != 0)
                    fwrite($mpdreport, "Warning for DVB check: Section 11.2.2- 'The frame rates used SHOULD be multiple integers of each other to enable seamless switching', not satisfied for Period $period_count Adaptation Set " . ($i+1) . "- Representation " . ($f1+1) . " and Representation " . ($f2+1) . ".\n");
            }
        }
    }
    ##
}

function DVB_audio_checks($adapt, $reps, $mpdreport, $i, $contentTemp_aud_found){
    global $adapt_audio_count, $main_audio_found, $period_count, $audio_bw;
    
    if($adapt->getAttribute('contentType') == 'audio'){
        $adapt_audio_count++;
    }
    
    $adapt_role_element_found = false;
    $rep_role_element_found = false;
    $contentComp_role_element_found = false;
    $adapt_audioChConf_element_found = false;
    $adapt_audioChConf_scheme = array();
    $adapt_audioChConf_value = array();
    $adapt_mimeType = $adapt->getAttribute('mimeType');
    $adapt_audioSamplingRate = $adapt->getAttribute('audioSamplingRate');
    $adapt_specific_role_count = 0;
    $adapt_codecs = $adapt->getAttribute('codecs');
    
    $ids = array();
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
            $adapt_audioChConf_value[] = $ch->getAttribute('value');
        }
        if($contentTemp_aud_found && $ch->nodeName == 'ContentComponent'){
            if($ch->getAttribute('contentType') == 'audio'){
                foreach($ch->childNodes as $c){
                    if($c->nodeName == 'Role'){
                        $contentComp_role_element_found = true;
                    }
                }
                $ids[] = $ch->getAttribute('id');
            }
        }
    }
    
    ## Information from this part is for Section 6.1: distinguishing Adaptation Sets
    if($adapt->getAttribute('contentType') == 'audio' && $adapt_specific_role_count == 0)
        fwrite($mpdreport, "###'DVB check violated: Section 6.1.2- Every audio Adaptation Set SHALL include at least one Role Element using the scheme \"urn:mpeg:dash:role:2011\" as defined in ISO/IEC 23009-1', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    ##
    
    ## Information from this part is for Section 6.3:Dolby and 6.4:DTS
    if(strpos($adapt_codecs, 'ec-3') !== FALSE || strpos($adapt_codecs, 'ac-4') !== FALSE){
        if(!empty($adapt_audioChConf_scheme)){
            if(strpos($adapt_codecs, 'ec-3') !== FALSE){
                if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme) && !in_array('urn:dolby:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme))
                    fwrite($mpdreport, "###'DVB check violated: Section E.2.5- For E-AC-3 the AudioChannelConfiguration element SHALL use either the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" or the legacy \"urn:dolby:dash:audio_channel_configuration:2011\" schemeURI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
                
                if(in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme)){
                    $value = $adapt_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme)];
                    if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                        fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
                }
            }
            if(strpos($adapt_codecs, 'ac-4') !== FALSE){
                if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme))
                    fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
                else{
                    $value = $adapt_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $adapt_audioChConf_scheme)];
                    if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                        fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
                }
            }
        }
    }
    if(strpos($adapt_codecs, 'dtsc') !== FALSE || strpos($adapt_codecs, 'dtsh') !== FALSE || strpos($adapt_codecs, 'dtse') !== FALSE || strpos($adapt_codecs, 'dtsi') !== FALSE){
        if(!empty($adapt_audioChConf_scheme) && !in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $adapt_audioChConf_scheme))
            fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " AudioChannelConfiguration.\n");
    }
    ##
    
    $reps_len = $reps->length;
    $rep_audioChConf_scheme = array();
    $rep_audioChConf_value = array();
    $subrep_audioChConf_scheme = array();
    $subrep_audioChConf_value = array();
    $dependencyIds = array();
    for($j=0; $j<$reps_len; $j++){
        $rep = $reps->item($j);
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
                $rep_audioChConf_value[] = $ch->getAttribute('value');
            }
            if($ch->nodeName == 'SubRepresentation'){
                $ind++;
                $subrep_codecs = $ch->getAttribute('codecs');
                foreach($ch->childNodes as $c){
                    if($c->nodeName == 'AudioChannelConfiguration'){
                        $subrep_audioChConf_scheme[] = $c->getAttribute('schemeIdUri');
                        $subrep_audioChConf_value[] = $ch->getAttribute('value');
                    }
                }
                
                ##Information from this part is for Section 6.3:Dolby and 6.4:DTS
                if(($adapt_codecs != '' && strpos($adapt_codecs, 'ec-3') !== FALSE) || ($rep_codecs != '' && strpos($rep_codecs, 'ec-3') !== FALSE) || (strpos($subrep_codecs, 'ec-3') !== FALSE)){
                    if(!empty($subrep_audioChConf_scheme)){
                        if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme) && !in_array('urn:dolby:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme))
                            fwrite($mpdreport, "###'DVB check violated: Section E.2.5- For E-AC-3 the AudioChannelConfiguration element SHALL use either the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" or the legacy \"urn:dolby:dash:audio_channel_configuration:2011\" schemeURI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
                        if(in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme)){
                            $value = $subrep_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme)];
                            if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                                fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
                        }
                    }
                }
                if(($adapt_codecs != '' && strpos($adapt_codecs, 'ac-4') !== FALSE) || ($rep_codecs != '' && strpos($rep_codecs, 'ac-4') !== FALSE) || (strpos($subrep_codecs, 'ec-3') !== FALSE)){
                    if(!empty($subrep_audioChConf_scheme)){
                        if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme))
                            fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
                        else{
                            $value = $subrep_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $subrep_audioChConf_scheme)];
                            if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                                fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
                        }
                    }
                }
                if((strpos($adapt_codecs, 'dtsc') !== FALSE || strpos($adapt_codecs, 'dtsh') !== FALSE || strpos($adapt_codecs, 'dtse') !== FALSE || strpos($adapt_codecs, 'dtsi') !== FALSE) ||
                   (strpos($rep_codecs, 'dtsc') !== FALSE || strpos($rep_codecs, 'dtsh') !== FALSE || strpos($rep_codecs, 'dtse') !== FALSE || strpos($rep_codecs, 'dtsi') !== FALSE) ||
                   (strpos($subrep_codecs, 'dtsc') !== FALSE || strpos($subrep_codecs, 'dtsc') !== FALSE || strpos($subrep_codecs, 'dtsc') !== FALSE || strpos($subrep_codecs, 'dtsc') !== FALSE)){
                    if(!empty($subrep_audioChConf_scheme) && !in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $subrep_audioChConf_scheme))
                        fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " SubRepresentation " . ($ind+1) . " AudioChannelConfiguration.\n");
                }
                ##
                
                ##Information from this part is for Section 11.3.0: audio stream bandwidth percentage
                if($contentTemp_aud_found){
                    if(in_array($ch->getAttribute('contentComponent'), $ids)){
                        $audio_bw[] = ($rep->getAttribute('bandwidth') != '') ? (float)($rep->getAttribute('bandwidth')) : (float)($ch->getAttribute('bandwidth'));
                    }
                }
                ##
            }
        }
        
        ##Information from this part is for Section 11.3.0: audio stream bandwidth percentage 
        if(!$contentTemp_aud_found){
            $audio_bw[] = (float)($rep->getAttribute('bandwidth'));
        }
        ##
        
        ##Information from this part is for Section 6.3:Dolby and 6.4:DTS
        if(($adapt_codecs != '' && strpos($adapt_codecs, 'ec-3') !== FALSE) || strpos($rep_codecs, 'ec-3') !== FALSE){
            if(!empty($rep_audioChConf_scheme)){
                if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme) && !in_array('urn:dolby:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme))
                    fwrite($mpdreport, "###'DVB check violated: Section E.2.5- For E-AC-3 the AudioChannelConfiguration element SHALL use either the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" or the legacy \"urn:dolby:dash:audio_channel_configuration:2011\" schemeURI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
                
                if(in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme)){
                    $value = $rep_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme)];
                    if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                        fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
                }
            }
        }
        if(($adapt_codecs != '' && strpos($adapt_codecs, 'ac-4') !== FALSE) || strpos($rep_codecs, 'ac-4') !== FALSE){
            if(!in_array('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme))
                fwrite($mpdreport, "###'DVB check violated: Section 6.3- For E-AC-3 and AC-4 the AudioChannelConfiguration element SHALL use the \"tag:dolby.com,2014:dash:audio_channel_configuration:2011\" scheme URI', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
            else{
                $value = $rep_audioChConf_value[array_search('tag:dolby.com,2014:dash:audio_channel_configuration:2011', $rep_audioChConf_scheme)];
                if(strlen($value) != 4 || (strlen($value) == 4 && !ctype_xdigit($value)))
                fwrite($mpdreport, "###'DVB check violated: Section 6.3- (For E-AC-3 and AC-4 the AudioChannelConfiguration element) the @value attribute SHALL contain four digit hexadecimal representation of the 16 bit field', found \"$value\" in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
            }
        }
        if((strpos($adapt_codecs, 'dtsc') !== FALSE || strpos($adapt_codecs, 'dtsh') !== FALSE || strpos($adapt_codecs, 'dtse') !== FALSE || strpos($adapt_codecs, 'dtsi') !== FALSE) ||
           (strpos($rep_codecs, 'dtsc') !== FALSE || strpos($rep_codecs, 'dtsh') !== FALSE || strpos($rep_codecs, 'dtse') !== FALSE || strpos($rep_codecs, 'dtsi') !== FALSE)){
            if(!empty($rep_audioChConf_scheme) && !in_array('tag:dts.com,2014:dash:audio_channel_configuration:2012', $rep_audioChConf_scheme))
                fwrite($mpdreport, "###'DVB check violated: Section 6.4- For all DTS audio formats AudioChannelConfiguration element SHALL use the \"tag:dts.com,2014:dash:audio_channel_configuration:2012\" for the @schemeIdUri attribute', conformance is not satisfied in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . " AudioChannelConfiguration.\n");
        }
        ##
        
        ## Information from this part is for Section 6.1 Table 3
        if($adapt_role_element_found == false && $contentComp_role_element_found == false && $rep_role_element_found == false)
            fwrite($mpdreport, "###'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', Role element could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
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
    global $period_count, $subtitle_bw;
    
    $adapt_mimeType = $adapt->getAttribute('mimeType');
    $adapt_codecs = $adapt->getAttribute('codecs');
    $adapt_type = $adapt->getAttribute('contentType');
    $contentComp = false;
    $contentComp_type = array();
    $subtitle = false;
    $supp_present = false; $supp_scheme = array(); $supp_val = array(); $supp_url = array(); $supp_fontFam = array(); $supp_mime = array();
    $ess_present = false; $ess_scheme = array(); $ess_val = array(); $ess_url = array(); $ess_fontFam = array(); $ess_mime = array();
    
    $ids = array();
    foreach($adapt->childNodes as $ch){
        if($ch->nodeName == 'ContentComponent'){
            $contentComp = true;
            $contentComp_type[] = $ch->getAttribute('contentType');
            if($ch->getAttribute('contentType') == 'text')
                $ids[] = $ch->getAttribute('contentType');
        }
        if($ch->nodeName == 'SupplementalProperty'){
            $supp_present = true;
            $supp_scheme[] = $ch->getAttribute('schemeIdUri');
            $supp_val[] = $ch->getAttribute('value');
            $supp_url[] = $ch->getAttribute('url');
            $supp_fontFam[] = $ch->getAttribute('fontFamily');
            $supp_mime[] = $ch->getAttribute('mimeType');
        }
        if($ch->nodeName == 'EssentialProperty'){
            $ess_present = true;
            $ess_scheme[] = $ch->getAttribute('schemeIdUri');
            $ess_val[] = $ch->getAttribute('value');
            $ess_url[] = $ch->getAttribute('url');
            $ess_fontFam[] = $ch->getAttribute('fontFamily');
            $ess_mime[] = $ch->getAttribute('mimeType');
        }
    }
    
    $reps_len = $reps->length;
    for($j=0; $j<$reps_len; $j++){
        $rep = $reps->item($j);
        
        $rep_codecs = $rep->getAttribute('codecs');
        $subrep_codecs = array();
        foreach ($rep->childNodes as $ch){
            if($ch->nodeName == 'SubRepresentation'){
                $subrep_codecs[] = $ch->getAttribute('codecs');
            
                ##Information from this part is for Section 11.3.0: audio stream bandwidth percentage
                if(in_array($ch->getAttribute('contentComponent'), $ids)){
                    $subtitle_bw[] = ($rep->getAttribute('bandwidth') != '') ? (float)($rep->getAttribute('bandwidth')) : (float)($ch->getAttribute('bandwidth'));
                }
                ##
            }
        }
        
        ## Information from this part is for Section 7.1: subtitle carriage
        if($adapt_mimeType == 'application/mp4' || $rep->getAttribute('mimeType') == 'application/mp4'){
            if(strpos($adapt_codecs, 'stpp') !== FALSE || strpos($rep_codecs, 'stpp') !== FALSE || in_array('stpp', $subrep_codecs) !== FALSE){
                $subtitle = true;
                
                if(($adapt_type != '' && $adapt_type != 'text') && !in_array('text', $contentComp_type))
                    fwrite($mpdreport, "###'DVB check violated: Section 7.1.1- The @contetnType attribute indicated for subtitles SHALL be \"text\"', found as ". $adapt->getAttribute('contentType') . " in Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                
                if($adapt->getAttribute('lang') == '')
                    fwrite($mpdreport, "###'DVB check violated: Section 7.1.2- In oder to allow a Player to identify the primary purpose of a subtitle track, the language attribute SHALL be set on the Adaptation Set', not found on Adaptaion Set ". ($i+1) . ".\n");
            }
            
            ##Information from this part is for Section 11.3.0: audio stream bandwidth percentage 
            if(! $contentComp){
                $subtitle_bw[] = (float)($rep->getAttribute('bandwidth'));
            }
            ##
        }
        ##
    }
    
    ## Information from this part is for Section 7.2: downloadable fonts and descriptors needed for them
    if($subtitle){
        if($supp_present){
            $x = 0;
            foreach($supp_scheme as $supp_scheme_i){
                if($supp_scheme_i == 'urn:dvb:dash:fontdownload:2014'){
                    if($supp_val[$x] != '1'){
                        fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- This descriptor (SupplementalProperty for downloadable fonts) SHALL use the values for @schemeIdUri and @value specified in clause 7.2.1.2', found as \"$supp_scheme_i\" and \"". $supp_val[$x] . "\" in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    if($supp_url[$x] == '' || $supp_fontFam[$x] == '' || $supp_mime[$x] != 'application/font-sfnt' || $supp_mime[$x] != 'application/font-woff'){
                        fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- The descriptor (SupplementalProperty for downloadable fonts) SHALL carry all the mandatory additional attributes defined in clause 7.2.1.3', not complete in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                }
                $x++;
            }
        }
        elseif($ess_present){
            $x = 0;
            foreach($ess_scheme as $ess_scheme_i){
                if($ess_scheme_i == 'urn:dvb:dash:fontdownload:2014'){
                    if($ess_val[$x] != '1'){
                        fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- This descriptor (EssentialProperty for downloadable fonts) SHALL use the values for @schemeIdUri and @value specified in clause 7.2.1.2', found as \"$ess_scheme_i\" and \"". $ess_val[$x] . "\" in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    if($ess_url[$x] == '' || $ess_fontFam[$x] == '' || $ess_mime[$x] != 'application/font-sfnt' || $ess_mime[$x] != 'application/font-woff'){
                        fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- The descriptor (EssentialProperty for downloadable fonts) SHALL carry all the mandatory additional attributes defined in clause 7.2.1.3', not complete in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                }
                $x++;
            }
        }
    }
    
    $all_supp = $adapt->getElementsByTagName('SupplementalProperty');
    $all_ess = $adapt->getElementsByTagName('EssentialProperty');
    foreach($all_supp as $supp) {
        if($supp->getAttribute('schemeIdUri') == 'urn:dvb:dash:fontdownload:2014' && $supp->getAttribute('value') == '1' && $supp->getAttribute('url') != '' && $supp->getAttribute('fontFamily') != '' && ($supp->getAttribute('mimeType') == 'application/font-sfnt' || $supp->getAttribute('mimeType') == 'application/font-woff')){
            if($supp->parentNode->nodeName != 'AdaptationSet')
                fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- A descriptor (EssentialProperty for downloadable fonts) with these properties SHALL only be placed within an AdaptationSet containing subtitle Representations', not found on Adaptation Set " . " in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        }
    }
    foreach($all_ess as $ess) {
        if($ess->getAttribute('schemeIdUri') == 'urn:dvb:dash:fontdownload:2014' && $ess->getAttribute('value') == '1' && $ess->getAttribute('url') != '' && $ess->getAttribute('fontFamily') != '' && ($supp->getAttribute('mimeType') == 'application/font-sfnt' || $ess->getAttribute('mimeType') == 'application/font-woff')){
            if($ess->parentNode->nodeName != 'AdaptationSet')
                fwrite($mpdreport, "###'DVB check violated: Section 7.2.1.1- A descriptor (EssentialProperty for downloadable fonts) with these properties SHALL only be placed within an AdaptationSet containing subtitle Representations', not found on Adaptation Set " . " in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        }
    }
    ##
}

function DVB_content_protection($adapt, $reps, $mpdreport, $i, $cenc){
    global $period_count;
    
    $mp4protection_count = 0;
    
    $default_KIDs = array();
    $contentProtection = $adapt->getElementsByTagName('ContentProtection');
    foreach ($contentProtection as $contentProtection_i){
        if($contentProtection_i->parentNode->nodeName != 'AdaptationSet')
            fwrite($mpdreport, "###'DVB check violated: Section 8.3- ContentProtection descriptor SHALL be placed at he AdaptationSet level', found at \"" . $contentProtection_i->parentNode->nodeName . "\" level in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        else{
            if($contentProtection_i->getAttribute('schemeIdUri') == 'urn:mpeg:dash:mp4protection:2011' && $contentProtection_i->getAttribute('value') == 'cenc'){
                $mp4protection_count++;
                $default_KIDs[] = $contentProtection_i->getAttribute('cenc:default_KID');
            }
        }
    }
    
    if($contentProtection->length != 0 && $mp4protection_count == 0){
        fwrite($mpdreport, "###'DVB check violated: Section 8.4- Any Adaptation Set containing protected content SHALL contain one \"mp4protection\" ContentProtection descriptor with @schemeIdUri=\"urn:mped:dash:mp4protection:2011\" and @value=\"cenć\", not found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
        if($cenc == '' || ($cenc != '' && empty($default_KIDs)))
            fwrite($mpdreport, "Warning for DVB check: Section 8.4- '\"mp4protection\" ContentProtection descriptor SHOULD include the extension defined in ISO/IEC 23001-7 clause 11.2', not found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
    }
}

function HbbTV_mpdvalidator($dom, $mpdreport){
    
    global $onRequest_array, $xlink_not_valid_array;
    
    if(!empty($onRequest_array))
    {
        $onRequest_k_v  = implode(', ', array_map(
        function ($v, $k) { return sprintf(" %s with index (starting from 0) '%s'", $v, $k); },
        $onRequest_array,array_keys($onRequest_array)));
        fwrite($mpdreport, "###'HbbTV check violated, MPD SHALL NOT have xlink:actuate set to onRequest', found in ".$onRequest_k_v."\n"); 
    }
    
    if(!empty($xlink_not_valid_array))
    {
        $xlink_not_valid_k_v  = implode(', ', array_map(
        function ($v, $k) { return sprintf(" %s with index (starting from 0) '%s'", $v, $k); },
        $xlink_not_valid_array,array_keys($xlink_not_valid_array)));
        fwrite($mpdreport, "###'HbbTV check violated, MPD invalid xlink:href', found in :".$xlink_not_valid_k_v."\n"); 
    }
    
    $mpd_string = $dom->saveXML();
    $mpd_bytes = strlen($mpd_string);
    if($mpd_bytes > 100*1024){
        fwrite($mpdreport, "###'HbbTV check violated: Section 4.5- The MPD size shall not exceed 100 Kbytes', found " . ($mpd_bytes/1024) . " Kbytes.\n");
    }
    
    //$docType=$dom->getElementsByTagName('!DOCTYPE');
    $docType=$dom->doctype;
    if($docType!==NULL)
       fwrite($mpdreport, "###'HbbTV check violated: The MPD must not contain an XML Document Type Definition(<!DOCTYPE>)', but found in the MPD \n");

    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    
    ## Warn on low values of MPD@minimumUpdatePeriod (for now the lowest possible value is assumed to be 1 second)
    if($MPD->getAttribute('minimumUpdatePeriod') != ''){
        $mup = timeparsing($MPD->getAttribute('minimumUpdatePeriod'));
        if($mup < 1)
            fwrite($mpdreport, "Warning for HbbTV check: 'MPD@minimumUpdatePeriod has a lower value than 1 second.\n");
    }
    ##
    
    // Periods within MPD
    $period_count = 0;
    foreach($MPD->childNodes as $node){
        if($node->nodeName == 'Period'){
            $period_count++;
           
            // Adaptation Sets within each Period
            $adapts = $node->getElementsByTagName('AdaptationSet');
            $adapt_count=0;
            $adapt_video_cnt=0;
            $adapt_audio_cnt=0;
            $main_video_found=0;
            $main_audio_found=0;
            //Following has error reporting code if MPD element is not part of validating profile.
            for($i=0; $i< ($adapts->length); $i++){
                $adapt_count++;
#                $subSegAlign=$adapts->item($i)->getAttribute('subsegmentAlignment');
#                if($subSegAlign == TRUE)
#                    fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentAlignment' as true in AdaptationSet ".($i+1)." \n");
                
               $role=$adapts->item($i)->getElementsByTagName('Role');
               if($role->length>0){
                    $schemeIdUri=$role->item(0)->getAttribute('schemeIdUri');
                    $role_value=$role->item(0)->getAttribute('value');
               }
                //Representation in AS and its checks
               $rep_count=0;
                $reps = $adapts->item($i)->getElementsByTagName('Representation');
                
                if($adapts->item($i)->getAttribute('contentType')=='video' || $adapts->item($i)->getAttribute('mimeType')=='video/mp4' || $reps->item(0)->getAttribute('mimeType')=='video/mp4')
                {    $adapt_video_cnt++;
                     if($role->length>0 && (strpos($schemeIdUri,"urn:mpeg:dash:role:2011")!==false && $role_value=="main"))
                           $main_video_found++;
                     HbbTV_VideoRepChecks($adapts->item($i), $adapt_count,$period_count,$mpdreport );
                }
     
                    
                if($adapts->item($i)->getAttribute('contentType')=='audio' || $adapts->item($i)->getAttribute('mimeType')=='audio/mp4' ||$reps->item(0)->getAttribute('mimeType')=='audio/mp4' )
                {   $adapt_audio_cnt++;
                    if($role->length>0 && (strpos($schemeIdUri,"urn:mpeg:dash:role:2011")!==false && $role_value=="main"))
                           $main_audio_found++;
                    HbbTV_AudioRepChecks($adapts->item($i), $adapt_count,$period_count,$mpdreport);
                }

                 //Following has error reporting code if MPD element is not part of validating profile.
#                $startWithSAP=$adapts->item($i)->getAttribute('subsegmentStartsWithSAP');
#                    if($startWithSAP == 1 || $startWithSAP ==2)
#                        fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " \n");
#                    else if ($startWithSAP==3){
#                        if(!($reps->length>1))
#                            fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " not containing more than one Representation \n");
#
#                      
#                    }
                for($j=0;$j<($reps->length);$j++){
                    $rep_count++;
#                    $baseURL=$reps->item($j)->getElementsByTagName('BaseURL');
#                    if($baseURL->length>0)
#                        fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an element that is not part of the HbbTV profile', i.e., found 'BaseURL' element in Representation ".($j+1)." of AdaptationSet ".($i+1). ". \n");
#                    if ($startWithSAP==3){
#                      $currentChild=$reps->item($j);
#                        $currentId= $currentChild->getAttribute('mediaStreamStructureId');
#                        while($currentChild && $currentId!=NULL){
#                            $currentChild=nextElementSibling($currentChild);
#                            if($currentChild!==NULL){
#                                $nextId=$currentChild->getAttribute('mediaStreamStructureId');
#                                if($currentId==$nextId){
#                                    fwrite($mpdreport, "###'HbbTV profile violated: The MPD contains an attribute that is not part of the HbbTV profile', i.e., found 'subsegmentStartsWithSAP' ".$startWithSAP." in AdaptationSet ".($i+1). " with same value of mediaStreamStructureId in more than one Representation \n");
#
#                                }
#                            }
#                        }
#                     }

                }
                if($rep_count>16)
                   fwrite($mpdreport, "###'HbbTV check violated: There shall be no more than 16 Representations per Adaptatation Set  in an MPD', but found ".$rep_count." Represenations in Adaptation Set ".$adapt_count." in Period ".$period_count." \n");

                
            }
            if($adapt_count>16)
                fwrite($mpdreport, "###'HbbTV check violated: There shall be no more than 16 Adaptation Sets per Period in an MPD', but found ".$adapt_count." Adaptation Sets in Period ".$period_count." \n");
            if($adapt_video_cnt==0)
                fwrite($mpdreport, "###'HbbTV check violated: There shall be at least one video Adaptation Set per Period in an MPD', but found ".$adapt_video_cnt." video Adaptation Sets in Period ".$period_count." \n");
            if($adapt_video_cnt>1 && $main_video_found!=1)
                fwrite($mpdreport, "###'HbbTV check violated: If there is more than one video AdaptationSet, exactly one shall be labelled with Role@value 'main' ', but found ".$main_video_found." Role@value 'main' in Period ".$period_count." \n");
            if($adapt_audio_cnt>1 && $main_audio_found!=1)
                fwrite($mpdreport, "###'HbbTV check violated: If there is more than one audio AdaptationSet, exactly one shall be labelled with Role@value 'main' ', but found ".$main_audio_found." Role@value 'main' in Period ".$period_count." \n");
            
        }  
        
    }
    if($period_count>32)
            fwrite($mpdreport, "###'HbbTV check violated: There shall be no more than 32 Periods in an MPD', but found ".$period_count." Periods \n");
  
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

function HbbTV_VideoRepChecks($adapt, $adapt_num,$period_num,$mpdreport)
{
    $width=$adapt->getAttribute('width');
    $height=$adapt->getAttribute('height');
    $frameRate=$adapt->getAttribute('frameRate');
    $scanType=$adapt->getAttribute('scanType');
    $codecs=$adapt->getAttribute('codecs');
    if($codecs!=NULL && strpos($codecs, 'avc')===false)
        fwrite($mpdreport, "###'HbbTV check violated: The video content referenced by MPD shall only be encoded using video codecs defined in 7.3.1 (AVC)', but ".$codecs." found in Adaptation Set ".$adapt_num." in Period ".$period_num." \n");

    
    $reps=$adapt->getElementsByTagName('Representation');
    for($i=0;$i<$reps->length;$i++)
    {
        if($width==NULL && $reps->item($i)->getAttribute('width')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @width information for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($height==NULL && $reps->item($i)->getAttribute('height')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @height information for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($frameRate==NULL && $reps->item($i)->getAttribute('frameRate')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @frameRate information for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($scanType==NULL && $reps->item($i)->getAttribute('scanType')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @scanType information for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($codecs==NULL && strpos($reps->item($i)->getAttribute('codecs'),'avc')===false)
            fwrite($mpdreport, "###'HbbTV check violated: The video content referenced by MPD shall only be encoded using video codecs defined in 7.3.1 (AVC)', but '".($reps->item($i)->getAttribute('codecs'))."' found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        
    }
}

function HbbTV_AudioRepChecks($adapt, $adapt_num,$period_num,$mpdreport)
{
    $SamplingRate=$adapt->getAttribute('audioSamplingRate');
    $lang=$adapt->getAttribute('lang');
    $channelConfig_adapt=$adapt->getElementsByTagName('AudioChannelConfiguration');
    $reps=$adapt->getElementsByTagName('Representation');
    
    $role=$adapt->getElementsByTagName('Role');
    if($role->length>0)
        $roleValue=$role->item(0)->getAttribute('value');
    
    $accessibility=$adapt->getElementsByTagName('Accessibility');
    if($accessibility->length>0)
        $accessibilityValue=$accessibility->item(0)->getAttribute('value');
    
    $codecs_adapt=$adapt->getAttribute('codecs');
    if($codecs_adapt!=NULL && strpos($codecs_adapt, 'mp4a')===false && strpos($codecs_adapt, 'ec-3')===false)
        fwrite($mpdreport, "###'HbbTV check violated: The audio content referenced by MPD shall only be encoded using video codecs defined in 7.3.1 (HE-AAC, E-AC-3)', but '".$codecs_adapt."' found in Adaptation Set ".$adapt_num." in Period ".$period_num." \n");

    
    for($i=0;$i<$reps->length;$i++)
    {
        if($SamplingRate==NULL && $reps->item($i)->getAttribute('audioSamplingRate')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @audioSamplingRate information for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($lang==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide @lang information inherited by all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if($roleValue=="commentary" &&  $accessibilityValue==1 && $reps->item($i)->getAttribute('dependencyId')==NULL)
            fwrite($mpdreport, "###'HbbTV check violated: For receiver mix audio description the associated audio stream shall use dependencyId ', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        
        if($codecs_adapt==NULL){
            $codecs=$reps->item($i)->getAttribute('codecs');
            $temp=strpos($codecs, 'mp4a');
            if(strpos($codecs, 'mp4a')===false && strpos($codecs, 'ec-3')===false)
                fwrite($mpdreport, "###'HbbTV check violated: The audio content referenced by MPD shall only be encoded using video codecs defined in 7.3.1 (HE-AAC, E-AC-3)', but '".$codecs."' found in Representation ".($i+1)." Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        }
        if($channelConfig_adapt->length==0){
            $channelConfig=$reps->item($i)->getElementsByTagName('AudioChannelConfiguration');
            if($channelConfig->length==0)
                fwrite($mpdreport, "###'HbbTV check violated: The profile-specific MPD shall provide AudioChannelConfiguration for all Representations', but not found for Representation ".($i+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
            else
                HbbTV_AudioChannelCheck($channelConfig,($codecs_adapt.$codecs),$i, $adapt_num,$period_num,$mpdreport);
        }
        else
            HbbTV_AudioChannelCheck($channelConfig_adapt,($codecs_adapt.$codecs),$i, $adapt_num,$period_num,$mpdreport);
    }
    
    
    
}

function HbbTV_AudioChannelCheck($channelConfig,$codecs,$rep_num, $adapt_num,$period_num,$mpdreport)
{
    $scheme=$channelConfig->item(0)->getAttribute("schemeIdUri");
    $value=$channelConfig->item(0)->getAttribute("value");
    if(strpos($codecs,'mp4a')!==false)
    {
        if(strpos($scheme,"urn:mpeg:dash:23003:3:audio_channel_configuration:2011")===false)
            fwrite($mpdreport, "###'HbbTV check violated: For HE-AAC the Audio Channel Configuration shall use urn:mpeg:dash:23003:3:audio_channel_configuration:2011 schemeIdURI', but this schemeIdUri not found for Representation ".($rep_num+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");

        if(!(is_numeric($value) && $value == round($value)))
            fwrite($mpdreport, "###'HbbTV check violated: For HE-AAC the Audio Channel Configuration shall use urn:mpeg:dash:23003:3:audio_channel_configuration:2011 schemeIdURI with value set to an integer number', but non-integer value found for Representation ".($rep_num+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");

    }
    else if (strpos($codecs,'ec-3')!==false)
    {
        if((strpos($scheme,"tag:dolby.com,2014:dash:audio_channel_configuration:2011")===false && strpos($scheme,"urn:dolby:dash:audio_channel_configuration:2011")===false))
            fwrite($mpdreport, "###'HbbTV check violated: For E-AC-3 the Audio Channel Configuration shall use either the tag:dolby.com,2014:dash:audio_channel_configuration:2011 or urn:dolby:dash:audio_channel_configuration:2011 schemeIdURI', but neither of these found for Representation ".($rep_num+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");
        if(strlen($value)!=4 || !ctype_xdigit($value))
            fwrite($mpdreport, "###'HbbTV check violated: For E-AC-3 the Audio Channel Configuration value shall contain a four digit hexadecimal number', but found value '".$value."' for Representation ".($rep_num+1)." of Adaptation Set ".$adapt_num." in Period ".$period_num." \n");

    }
}


function xlink_reconstruct_MPD($dom_MPD)
{
    global $reconstructed_MPD, $stop, $locate;
    global $onRequest_array, $xlink_not_valid_array;
    $onRequest_array = array(); //array to specify the period where the invalidation was found
    $xlink_not_valid_array = array();
    $stop = 0;
    $new_dom = new DOMDocument('1.0');
    $new_dom_node = $new_dom->importNode($dom_MPD, true);
    $new_dom->appendChild($new_dom_node);
    
    xlink_reconstruct_MPD_recursive($new_dom);
    //check the final MPD
    $reconstructed_MPD_st = $reconstructed_MPD->saveXML();
    $temp_file= fopen($locate ."/content_checker.txt", "w");
    fwrite($temp_file, $reconstructed_MPD_st);
    fclose($temp_file);  
}  

    function xlink_reconstruct_MPD_recursive($dom_MPD) //give $dom_sxe as argument when calling function 
    { 
        //global $locate;
        global $onRequest_array, $xlink_not_valid_array;
        global $reconstructed_MPD, $stop; //we need the stop value to prohibit the recursion from modifing the MPD with the stack instructions after it has be reconstructed 
        $reconstructed_MPD = new DOMDocument('1.0');
        $reconstructed_MPD->preserveWhiteSpace = false;
        $reconstructed_MPD->formatOutput = true;
        $MPD = $dom_MPD->getElementsByTagName('MPD')->item(0);
        $reconstructed_node = $reconstructed_MPD->importNode($MPD, true);
        $reconstructed_MPD->appendChild($reconstructed_node);

        $element_name = array(); 
        foreach ($dom_MPD->getElementsByTagName('*') as $node)
        { // search for all nodes within mpd   
            $node_name = $node->nodeName;
            $node_id = $node->getAttribute('id');
            $element_name[] = $node_name;
            $xlink=$node->getAttribute('xlink:href');
            if (($xlink != "") && ($stop === 0)) //stop needed to stop the recursion from making further modifications after MPD is reconstructed fully
            {
                $name_repetition = array_count_values($element_name);
                $index_for_modifications = $name_repetition[$node_name] - 1; //this will be the index for replacing and inserting the xlink nodes
                    
                $actuate_mode = $node->getAttribute('xlink:actuate');
                
                if($actuate_mode === 'onRequest')// check if actuate mode is onRequest
                {
                    $onRequest_array[$index_for_modifications] = $node_name;
                }
              
                //if you have a valid url then get the content even if it is onRequest
                $xlink_url = get_headers($xlink);
                if(!strpos($xlink_url[0], "200")) 
                {
                    $xlink_not_valid_array[$index_for_modifications] = $node_name;
                    $reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications)->parentNode->removeChild($reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications)); 
                }
                else 
                {
                    //get contents and turn them in xml format
                    $xlink_content = file_get_contents($xlink);
                    $xlink_content = '<elements xmlns="urn:mpeg:dash:schema:mpd:2011" xmlns:xlink="http://www.w3.org/1999/xlink">'."\n".$xlink_content;
                    $xlink_content = $xlink_content."\n"."</elements>";
                    $xlink_content = simplexml_load_string($xlink_content);
                    $dom_xlink = dom_import_simplexml($xlink_content);
                    
                    $dom = new DOMDocument('1.0');
                    $dom_xlink = $dom->importNode($dom_xlink, true);
                    $dom->appendChild($dom_xlink);
                    $first_element_checker = 0; //the first element will be replaced with an existing one while the other will be just inserted after that
                    foreach ($dom->documentElement->childNodes as $dom_node) 
                    {
                        if ($dom_node->nodeName === $node_name)
                        {
                            $xlink = $dom_node->getAttribute('xlink:href');
                            //first period is replaced with the one with the same index and others are just inserted after the first one
                            $first_element_checker ++;
                            $dom_node1 = $reconstructed_MPD->importNode($dom_node, true); //necessary to use replacechild or removechild
                            if($first_element_checker === 1)
                            {
                                $reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications)->parentNode->replaceChild($dom_node1, $reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications));
                            }
                            else
                            {
                                $reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications)->parentNode->insertBefore($dom_node1, $reconstructed_MPD->getElementsByTagName($node_name)->item($index_for_modifications)->nextSibling);  
                            }
                        }      
                    }
                    xlink_reconstruct_MPD_recursive($reconstructed_MPD);
                }
            }       
        }         
        $stop = 1; // now don't do any more modifications to the MPD  
    }