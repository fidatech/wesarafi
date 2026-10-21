<?php

/**
 * Export to PHP Array plugin for PHPMyAdmin
 * @version 4.9.5
 */

use Carbon\Carbon;

/**
 * Database `auth`
 */

/* `auth`.`provinces` */
$provinces = array(
  array('id' => '1', 'name_en' => 'KABUL', 'name_fa' => 'کابل', 'name_pa' => 'کابل', 'country_id' => 1),
  array('id' => '2', 'name_en' => 'KAPISA', 'name_fa' => 'کاپیسا', 'name_pa' => 'کاپیسا', 'country_id' => 1),
  array('id' => '3', 'name_en' => 'PARWAN', 'name_fa' => 'پروان', 'name_pa' => 'پروان', 'country_id' => 1),
  array('id' => '4', 'name_en' => 'MAIDAN WARDAK', 'name_fa' => 'میدان وردگ', 'name_pa' => 'میدان وردگ', 'country_id' => 1),
  array('id' => '5', 'name_en' => 'LOGAR', 'name_fa' => 'لوگر', 'name_pa' => 'لوگر', 'country_id' => 1),
  array('id' => '6', 'name_en' => 'NANGARHAR', 'name_fa' => 'ننگرهار', 'name_pa' => 'ننگرهار', 'country_id' => 1),
  array('id' => '7', 'name_en' => 'LAGHMAN', 'name_fa' => 'لغمان', 'name_pa' => 'لغمان', 'country_id' => 1),
  array('id' => '8', 'name_en' => 'PANJSHER', 'name_fa' => 'پنجشیر', 'name_pa' => 'پنجشیر', 'country_id' => 1),
  array('id' => '9', 'name_en' => 'BAGHLAN', 'name_fa' => 'بغلان', 'name_pa' => 'بغلان', 'country_id' => 1),
  array('id' => '10', 'name_en' => 'BAMYAN', 'name_fa' => 'بامیان', 'name_pa' => 'بامیان', 'country_id' => 1),
  array('id' => '11', 'name_en' => 'GHAZNI', 'name_fa' => 'غزنی', 'name_pa' => 'غزنی', 'country_id' => 1),
  array('id' => '12', 'name_en' => 'PAKTIKA', 'name_fa' => 'پکتیکا', 'name_pa' => 'پکتیکا', 'country_id' => 1),
  array('id' => '13', 'name_en' => 'PAKTYA', 'name_fa' => 'پکتیا', 'name_pa' => 'پکتیا', 'country_id' => 1),
  array('id' => '14', 'name_en' => 'KHOST', 'name_fa' => 'خوست', 'name_pa' => 'خوست', 'country_id' => 1),
  array('id' => '15', 'name_en' => 'KUNAR', 'name_fa' => 'کنر', 'name_pa' => 'کنر', 'country_id' => 1),
  array('id' => '16', 'name_en' => 'NOORISTAN', 'name_fa' => 'نورستان', 'name_pa' => 'نورستان', 'country_id' => 1),
  array('id' => '17', 'name_en' => 'BADAKHSHAN', 'name_fa' => 'بدخشان', 'name_pa' => 'بدخشان', 'country_id' => 1),
  array('id' => '18', 'name_en' => 'TAKHAR', 'name_fa' => 'تخار', 'name_pa' => 'تخار', 'country_id' => 1),
  array('id' => '19', 'name_en' => 'KUNDUZ', 'name_fa' => 'کندوز', 'name_pa' => 'کندوز', 'country_id' => 1),
  array('id' => '20', 'name_en' => 'SAMANGAN', 'name_fa' => 'سمنگان', 'name_pa' => 'سمنگان', 'country_id' => 1),
  array('id' => '21', 'name_en' => 'BALKH', 'name_fa' => 'بلخ', 'name_pa' => 'بلخ', 'country_id' => 1),
  array('id' => '22', 'name_en' => 'SAR-E-PUL', 'name_fa' => 'سرپل', 'name_pa' => 'سرپل', 'country_id' => 1),
  array('id' => '23', 'name_en' => 'GHOR', 'name_fa' => 'غور', 'name_pa' => 'غور', 'country_id' => 1),
  array('id' => '24', 'name_en' => 'DAYKUNDI', 'name_fa' => 'دایکندی', 'name_pa' => 'دایکندی', 'country_id' => 1),
  array('id' => '25', 'name_en' => 'UROZGAN', 'name_fa' => 'اورزگان', 'name_pa' => 'اورزگان', 'country_id' => 1),
  array('id' => '26', 'name_en' => 'ZABUL', 'name_fa' => 'زابل', 'name_pa' => 'زابل', 'country_id' => 1),
  array('id' => '27', 'name_en' => 'KANDAHAR', 'name_fa' => 'کندهار', 'name_pa' => 'کندهار', 'country_id' => 1),
  array('id' => '28', 'name_en' => 'JAWZJAN', 'name_fa' => 'جوزجان', 'name_pa' => 'جوزجان', 'country_id' => 1),
  array('id' => '29', 'name_en' => 'FARYAB', 'name_fa' => 'فاریاب', 'name_pa' => 'فاریاب', 'country_id' => 1),
  array('id' => '30', 'name_en' => 'HELMAND', 'name_fa' => 'هــلـمــند', 'name_pa' => 'هــلـمــند', 'country_id' => 1),
  array('id' => '31', 'name_en' => 'BADGHIS', 'name_fa' => 'بادغیس', 'name_pa' => 'بادغیس', 'country_id' => 1),
  array('id' => '32', 'name_en' => 'HERAT', 'name_fa' => 'هرات', 'name_pa' => 'هرات', 'country_id' => 1),
  array('id' => '33', 'name_en' => 'FARAH', 'name_fa' => 'فراه', 'name_pa' => 'فراه', 'country_id' => 1),
  array('id' => '34', 'name_en' => 'NIMROZ', 'name_fa' => 'نیمروز', 'name_pa' => 'نیمروز', 'country_id' => 1),
  array('id' => '35', 'name_en' => 'UNKNOWN', 'name_fa' => 'نا معلوم', 'name_pa' => 'نا معلوم', 'country_id' => 1)
);


return $provinces;
