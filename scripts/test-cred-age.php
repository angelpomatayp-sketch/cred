<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/lib/bootstrap.php';
$count=0;
function expectAge(bool $ok,string $label): void { global $count; if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n"; }
foreach([
 ['2026-10-09','2026-10-09',true,'birth day'],
 ['2026-10-10','2026-10-09',false,'before birth'],
 ['2014-10-10','2026-10-09',true,'last day before twelve'],
 ['2014-10-09','2026-10-09',false,'twelfth birthday'],
 ['2000-01-01','2026-10-09',false,'adult'],
 ['2016-02-29','2028-02-28',true,'leap birth before birthday'],
 ['2016-02-29','2028-02-29',false,'leap birthday'],
 ['2092-02-29','2104-02-29',false,'century leap boundary']
] as [$birth,$date,$expected,$label])expectAge(credEligible($birth,$date)===$expected,$label);
expectAge(credStage('2026-10-01','2026-10-29')['label']==='Recién nacido · 0–28 días','28 days neonatal');
expectAge(credStage('2026-10-01','2026-10-30')['label']==='Menor de un año · desde 29 días','29 days infant');
foreach([1=>'Un año',2=>'Dos a cuatro años',5=>'Cinco a once años',12=>'Fuera del rango CRED'] as $age=>$label)expectAge(credStage('2020-01-31',sprintf('%04d-01-31',2020+$age))['label']===$label,'group boundary '.$age);
expectAge(credAgeLabel('2020-01-01','2021-02-03')==='1 años · 1 meses · 2 días','exact age on care date');
expectAge(credBirthday('2088-02-29',12)==='2100-02-28','calendar clamping without March overflow');
echo "$count age checks passed.\n";
