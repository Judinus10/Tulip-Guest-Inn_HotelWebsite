<?php
declare(strict_types=1);
require_once __DIR__ . '/../rooms/occupancy-helper.php';

function ensure_booking_guest_schema(PDO $pdo): void
{
    try { if ($pdo->query('SELECT id FROM booking_guest_occupancy_migration WHERE id = 1')->fetchColumn()) return; }
    catch (PDOException $e) { if ((int) ($e->errorInfo[1] ?? 0) !== 1146) throw $e; }
    if ($pdo->inTransaction()) throw new LogicException('Guest schema must be initialized before transactions.');
    $name = 'tulip-guests-' . substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,32);
    $q=$pdo->prepare('SELECT GET_LOCK(?,30)');$q->execute([$name]);
    if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Guest settings are being initialized. Retry shortly.');
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS booking_guest_occupancy_migration (id TINYINT PRIMARY KEY, completed_at DATETIME NOT NULL) ENGINE=InnoDB');
        if ($pdo->query('SELECT id FROM booking_guest_occupancy_migration WHERE id=1')->fetchColumn()) return;
        ensure_room_occupancy_schema($pdo);
        $cols=array_flip($pdo->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN));
        foreach (['adults'=>'INT UNSIGNED NULL','children'=>'INT UNSIGNED NOT NULL DEFAULT 0','children_ages_json'=>'TEXT NULL','occupancy_snapshot_json'=>'TEXT NULL'] as $col=>$definition) {
            if (!isset($cols[$col])) $pdo->exec("ALTER TABLE bookings ADD COLUMN $col $definition");
        }
        $pdo->beginTransaction();
        // Historical bookings do not contain an age breakdown. Preserve their guest totals.
        $pdo->exec('UPDATE bookings SET adults=guests WHERE adults IS NULL');
        $pdo->exec('INSERT INTO booking_guest_occupancy_migration VALUES (1,NOW())');$pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction())$pdo->rollBack();throw $e; }
    finally { $q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$name]); }
}

function booking_party_input(array $data, string $legacyKey='guests', bool $allowZeroAdults=false): array
{
    $legacy = $data[$legacyKey] ?? null;
    $explicit = array_key_exists('adults',$data) || array_key_exists('children',$data) || array_key_exists('children_ages',$data);
    $adults=room_occupancy_integer($explicit ? ($data['adults'] ?? 0) : ($legacy ?? 1),$allowZeroAdults?0:1,20,'Adults');
    $children=room_occupancy_integer($data['children'] ?? 0,0,19,'Children');
    $ages=$data['children_ages'] ?? [];
    if (is_string($ages)) $ages=json_decode($ages,true);
    if (!is_array($ages)||!array_is_list($ages)||count($ages)!==$children)throw new InvalidArgumentException('Enter one age at check-in for every child.');
    foreach ($ages as &$age) $age=room_occupancy_integer($age,0,17,'Child age at check-in'); unset($age);
    $guests=$adults+$children;
    if ($guests<1||$guests>20)throw new InvalidArgumentException('A reservation must contain 1 to 20 people.');
    if ($legacy!==null && room_occupancy_integer($legacy,1,20,'Total guests')!==$guests)throw new InvalidArgumentException('Total guests must equal adults plus children.');
    return ['adults'=>$adults,'children'=>$children,'children_ages'=>$ages,'guests'=>$guests];
}

function booking_room_occupancy(array $room,array $party,bool $countChildren): array
{
    $cutoff=(int)($room['max_child_age']??11);
    $qualifying=count(array_filter($party['children_ages'],static fn(int $age):bool=>$age<=$cutoff));
    $adultPlaces=$party['adults']+$party['children']-$qualifying;
    $capacity=(int)($room['max_guests']??$room['guests']??0);
    $maxAdults=(int)($room['max_adults']??$capacity);
    $maxChildren=(int)($room['max_children']??0);
    $maxTotal=(int)($room['max_total_occupants']??$capacity);
    $fits=$adultPlaces<=$maxAdults && $qualifying<=$maxChildren && $party['guests']<=$maxTotal
        && ($countChildren?$party['guests']:$adultPlaces)<=$capacity;
    return ['fits'=>$fits,'guest_capacity'=>$capacity,'adult_places'=>$adultPlaces,'child_places'=>$qualifying,
        'max_adults'=>$maxAdults,'max_children'=>$maxChildren,'max_total_occupants'=>$maxTotal,
        'max_child_age'=>$cutoff,'count_children_toward_capacity'=>$countChildren];
}

function booking_require_room_occupancy(PDO $pdo,array $room,array $party): array
{
    $snapshot=booking_room_occupancy($room,$party,room_count_children_setting($pdo));
    if (!$snapshot['fits'])throw new InvalidArgumentException('The selected adults and child ages do not fit '.($room['room_name']??'this room').'. Choose another room or additional rooms.');
    return $snapshot;
}

function booking_save_party(PDO $pdo,int $bookingId,array $party,array $snapshot): void
{
    if (!$pdo->inTransaction())throw new LogicException('Guest details must be saved inside the booking transaction.');
    $pdo->prepare('UPDATE bookings SET adults=?,children=?,children_ages_json=?,occupancy_snapshot_json=? WHERE id=?')
        ->execute([$party['adults'],$party['children'],json_encode($party['children_ages'],JSON_THROW_ON_ERROR),json_encode($snapshot,JSON_THROW_ON_ERROR),$bookingId]);
}

function booking_stored_party(array $row): array
{
    return ['adults'=>(int)($row['adults']??$row['guests']??0),'children'=>(int)($row['children']??0),
        'children_ages'=>json_decode((string)($row['children_ages_json']??'[]'),true)?:[]];
}

function booking_party_allocations(array $requested,array $total): array
{
    $parties=[];$adults=0;$ages=[];
    foreach($requested as $entry){
        if(!is_array($entry))throw new InvalidArgumentException('Invalid room allocation.');
        $id=room_occupancy_integer($entry['room_id']??0,1,PHP_INT_MAX,'Room ID');
        if(isset($parties[$id]))throw new InvalidArgumentException('Choose each room only once.');
        $party=booking_party_input($entry,'guests',true);$parties[$id]=$party;
        $adults+=$party['adults'];$ages=array_merge($ages,$party['children_ages']);
    }
    $expected=$total['children_ages'];sort($ages);sort($expected);
    if(!$parties||$adults!==$total['adults']||$ages!==$expected)throw new InvalidArgumentException('Allocate every adult and child exactly once across the rooms.');
    return $parties;
}
