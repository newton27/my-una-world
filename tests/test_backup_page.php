<?php
$page=require __DIR__.'/fixtures/backup_page.php';
$html=$page->renderBackups();
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);libxml_clear_errors();
$xp=new DOMXPath($dom);$failures=array();$checks=0;
function checkPage($ok,$name){global $failures,$checks;$checks++;if(!$ok)$failures[]=$name;}
checkPage($xp->query('//article[contains(@class,"muw-backup-card")]')->length===3,'each archive has a readable backup card');
checkPage($xp->query('//dt[text()="Started"]')->length===3 && $xp->query('//dt[text()="Completed"]')->length===3 && $xp->query('//dt[text()="Duration"]')->length===3,'all three timing fields are labeled per backup');
checkPage(str_contains($html,'2026-11-01 15:00:00 UTC'),'known completion time is visible with timezone');
checkPage(substr_count($html,'Not recorded')>=2,'legacy completion and duration remain explicitly unknown');
checkPage(!str_contains($html,'<script>alert') && str_contains($html,'&lt;script&gt;'),'backup descriptions cannot inject markup');
checkPage($xp->query('//form[input[@name="muw_action" and @value="delete_backup"] and input[@name="backup_id" and @value="2"]]')->length===0,'protected rollback backup has no delete action');
checkPage($xp->query('//form[input[@name="muw_action" and @value="restore"]]/input[@name="confirmation" and @placeholder="RESTORE"]')->length===2,'full restore still requires typed confirmation');
checkPage($xp->query('//form[not(input[@name="csrf" and @value="fixture-csrf"])]')->length===0,'every action retains its CSRF token');
checkPage($xp->query('//details[summary[contains(text(),"Recovery actions")]]//button[text()="Delete"]')->length===2,'destructive actions stay inside an explicit recovery group');
checkPage(str_contains($html,'Database export') && str_contains($html,'fixture_rows'),'phase timing and slow-table evidence are available for review');
BxDolModule::$instance->active=array('id'=>4,'created_at'=>time()-900,'started_at'=>time()-600,'finished_at'=>0,'mode'=>'full','status'=>'running','progress'=>15,'stage'=>'Database','message'=>'Table 1 of 2: fixture_rows | Rows exported: 500.');
$active=$page->renderBackups();
checkPage(str_contains($active,'id="muw-job-started"') && str_contains($active,'id="muw-job-duration"'),'active backups display start time and elapsed duration');
if($failures){fwrite(STDERR,"Backups page failures:\n- ".implode("\n- ",$failures)."\n");exit(1);}
if(getenv('MUW_PREVIEW_OUTPUT'))file_put_contents(getenv('MUW_PREVIEW_OUTPUT'),$html);
echo "Backups page assertions passed: $checks\n";
