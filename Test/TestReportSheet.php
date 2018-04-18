<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\IOFactory;
// Open the test report spreadsheet and write mpdURL and all error logs.
function CreateTestReport($mpdURL, $folder)
{           

global $line_count;
$inputFileName = 'TestReport.ods';
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
$spreadsheet = $reader->load($inputFileName);
     file_put_contents("tempERrrororrororo.txt", "i came herenow");

$sheet = $spreadsheet->getActiveSheet();
$highCell= $sheet->getHighestDataRow();


$RepLogFiles=glob($folder.'/*log.txt');
$CrossValidDVB=glob($folder.'/*compInfo.txt');
$CrossRepDASH=glob($folder.'/*CrossInfofile.txt');
$line_count=2;//Leave one empty line after each mpd log.

//Start logging with mpd errors
$mpdReport=file_get_contents($folder.'/mpdreport.txt');
$mpdError=0;
$array=array('XLink resolving successful','MPD validation successful','Schematron validation successful');
foreach ($array as $string)
{
    if(strpos($mpdReport, $string)===false)
            $mpdError=1;
}
    
if($mpdError)
        WriteLineToSheet($mpdReport,$sheet,$highCell);
else
{
    if(strpos($mpdReport, "###")===false && strpos($mpdReport, "Warning")===false)
        echo "No mpd error/warning";
    else
    {
        $pos=strpos($mpdReport,"HbbTV-DVB Validation");
        $new_contents= substr($mpdReport, $pos);
        WriteLineToSheet($new_contents,$sheet,$highCell);
    }
               
}
    


foreach($RepLogFiles as $file)
{
    $contents=file_get_contents($file);
    WriteLineToSheet($contents,$sheet,$highCell);
}

foreach($CrossValidDVB as $file)
{
    $contents=file_get_contents($file);
    if(strpos($contents, "###")===false)
            if(strpos($contents, "Warning")===false)
                    continue;
       
    WriteLineToSheet($contents,$sheet,$highCell);
}
foreach($CrossRepDASH as $file)
{
    $contents=file_get_contents($file);
    if(strpos($contents, "Error")===false)
            continue;
    
    WriteLineToSheet($contents,$sheet,$highCell);
}
//Print mpd at the end but in column B.
$sheet->setCellValue('B'.($highCell+$line_count-1), $mpdURL);

$writer = new Ods($spreadsheet);
$writer->save('TestReport.ods');
}


function WriteLineToSheet($contents,$sheet,$highCell)
{
    global $line_count;
    $lines=explode(PHP_EOL, $contents);
    foreach($lines as $line)
    {
        $sheet->setCellValue('A'.($highCell+$line_count), $line);
        $line_count++;
    }
}
?>
