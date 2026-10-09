<?php
declare(strict_types=1);
// NTS 238-MINSA/DGIESP-2025, sections 1 and 6.3–6.4.
// Calendar implementation: birth inclusive, twelfth birthday exclusive.
function credBirthday(string $birth, int $years): string {
    $d=new DateTimeImmutable(validDate($birth),new DateTimeZone('America/Lima'));
    $year=(int)$d->format('Y')+$years; $month=(int)$d->format('m');
    $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month),new DateTimeZone('America/Lima'));
    return $first->setDate($year,$month,min((int)$d->format('d'),(int)$first->format('t')))->format('Y-m-d');
}
function credEligible(string $birth,string $on): bool {
    validDate($birth); validDate($on);
    return $on >= $birth && $on < credBirthday($birth,12);
}
function requireCredAge(string $birth,string $on,string $context='la atención'): void {
    if(!credEligible($birth,$on)) throw new InvalidArgumentException('Edad fuera del rango CRED para '.$context.'. Corresponde desde el nacimiento hasta antes de cumplir 12 años (11 años, 11 meses y 29 días según la NTS 238). El historial se conserva.');
}
function credAgeLabel(string $birth,string $on): string {
    validDate($birth);validDate($on);
    if($on<$birth)return 'Aún no ha nacido';
    $d=(new DateTimeImmutable($birth))->diff(new DateTimeImmutable($on));
    return $d->y.' años · '.$d->m.' meses · '.$d->d.' días';
}
function credStage(string $birth,string $on): array {
    if(!credEligible($birth,$on))return ['label'=>'Fuera del rango CRED','guidance'=>'Se conserva la ficha y el historial. No corresponde una nueva atención CRED en esta fecha.'];
    $days=(int)(new DateTimeImmutable($birth))->diff(new DateTimeImmutable($on))->days;
    if($days<=28)return ['label'=>'Recién nacido · 0–28 días','guidance'=>'Tres controles neonatales. La oportunidad del primero depende del nacimiento y del alta; los siguientes requieren revisar el historial y la norma.'];
    if($on<credBirthday($birth,1))return ['label'=>'Menor de un año · desde 29 días','guidance'=>'Siete controles en el esquema regular: 1, 2, 3, 4, 6, 7 y 9 meses. Los intervalos varían según el control.'];
    if($on<credBirthday($birth,2))return ['label'=>'Un año','guidance'=>'Cuatro controles trimestrales; intervalo mínimo regular de 90 días desde el último control.'];
    if($on<credBirthday($birth,5))return ['label'=>'Dos a cuatro años','guidance'=>'Dos controles semestrales por año; intervalo mínimo regular de 180 días desde el último control.'];
    return ['label'=>'Cinco a once años','guidance'=>'Un control anual; intervalo mínimo regular de 360 días desde el último control.'];
}
function credContext(string $birth,string $on): array {
    return ['eligible'=>credEligible($birth,$on),'age'=>credAgeLabel($birth,$on),'last_date'=>(new DateTimeImmutable(credBirthday($birth,12)))->modify('-1 day')->format('Y-m-d'),...credStage($birth,$on)];
}
