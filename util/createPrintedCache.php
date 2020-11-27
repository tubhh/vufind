<?php
$cacheDir = dirname(__FILE__).'/../local/cache';
                        if (file_exists($cacheDir.'/holdings/sfxprinted.xml')) {
                            $printedholdings = file_get_contents($cacheDir.'/holdings/sfxprinted.xml');
                        } else {
                            $printedholdings = file_get_contents('https://www.tub.tuhh.de/ext/holdings/sfxprinted.xml');
                        }
                        $dom = new DomDocument();
                        $dom->loadXML($printedholdings);
                        $items = $dom->documentElement->getElementsByTagName('item');
                        foreach ($items as $item) {
                            $issnArray = $item->getElementsByTagName('issn');
                            foreach ($issnArray as $issnVar) {
                                $issntocheck = str_replace('-', '', $issnVar->nodeValue);
                                $cacheFile = fopen($cacheDir.'/holdings/'.$issntocheck.'.obj', "w");
                                    $coverages = $item->getElementsByTagName('coverage');
                                    foreach ($coverages as $coverage) {
                                        $covstart = $coverage->getElementsByTagName('from')->item(0)->getElementsByTagName('year')->item(0)->nodeValue;
                                        $covend = null;
                                        if ($coverage->getElementsByTagName('to')->item(0)->getElementsByTagName('year')->length > 0) {
                                            $covend = $coverage->getElementsByTagName('to')->item(0)->getElementsByTagName('year')->item(0)->nodeValue;
                                        }
                                        fputs($cacheFile, $covstart."\t".$covend."\n");
                                    }
                                fclose($cacheFile);
                                }
                            }

?>