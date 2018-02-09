<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
 function CrossValidation_HbbTV_DVB_Segments($hbbtv,$dvb)
 {
    common_crossValidation();
    
     if($hbbtv)
         crossValidation_HbbTV_Segments();
     
     if($dvb)
         crossValidation_DVB_Segments();

     
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
 
 
