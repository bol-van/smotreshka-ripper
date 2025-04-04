<?php

 $ua = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0";
 $cookfile = "cooks.txt";
 $eol="\r\n";

 if ($argc<4)
 {
  echo "smotreshka.php <email> <password> <playlist_file> [all|split|id|selectmax]\n";
  die();
 }
 $sm_email=$argv[1];
 $sm_password=$argv[2];
 $playlist_file=$argv[3];
 $mode = ($argc>=5) ? $argv[4] : "Auto";
 $sm_email_urlencoded=urlencode($sm_email);

 function StructArraySearch(array &$a,$field,$value,$default_idx=FALSE)
 {
  foreach($a as $i => $v)
   if (strtolower($v->{$field})==strtolower($value))
    return $i;
  return $default_idx;
 }
 function SearchMaxQualityId(array &$a)
 {
  $maxq_id=0;
  $maxq=0;
  foreach($a as $i => $v)
  {
   $q = floatval($v->id);
   if ($q>$maxq)
   {
    $maxq_id=$i;
    $maxq=$q;
   }
  }
  return $maxq_id;
 }

 function CheckHttpCode($curl,$expected_code=200)
 {
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($httpcode!=$expected_code) throw new Exception("http code $httpcode in ".curl_getinfo($curl,CURLINFO_EFFECTIVE_URL));
 }
 function CheckHttpCodeChannel($curl)
 {
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $url = curl_getinfo($curl,CURLINFO_EFFECTIVE_URL);
  if ($httpcode==404)
  {
        echo "fetch error $httpcode for url : $url\n";
        return false;
  }
  if ($httpcode!=200) throw new Exception("http code $httpcode in $url");
  return true;
 }

 function create_m3u($fname)
 {
  global $eol;
  $f=fopen($fname, "w");
  if (!$f) throw new Exception("could not create $fname");
  fwrite($f,"#EXTM3U$eol");
  return $f;
 }
 function write_m3u_chn($file,$title,$url)
 {
  global $eol;
  fwrite($file,"#EXTINF:-1,$title$eol$url$eol");
 }


 $err=0;
 $curl = curl_init();
 $fplaylist = false;
 $ids = array();
 try
 {
  curl_setopt_array($curl, array(
        CURLOPT_COOKIEFILE => $cookfile,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_TIMEOUT_MS => 5000,
        CURLOPT_CONNECTTIMEOUT_MS => 5000,
        CURLOPT_SSL_VERIFYPEER=>false
  ));

  curl_setopt ($curl, CURLOPT_POSTFIELDS, "email=$sm_email_urlencoded&password=$sm_password");
  curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/login");
  curl_exec($curl);
  CheckHttpCode($curl);

  curl_setopt ($curl, CURLOPT_POST, false);
  curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/channels");
  $resp = curl_exec($curl);
  CheckHttpCode($curl);
  $json = json_decode($resp);
  if (!isset($json)) throw new Exception("bad channels json");

  $rends = array();
  foreach($json->channels as $ch)
  {
   $info = $ch->info;
   if ($info->purchaseInfo->bought)
   {
    $title = $info->metaInfo->title;
    curl_setopt ($curl, CURLOPT_URL, "https://fe.smotreshka.tv/playback-info/".$ch->id);
    $resp = curl_exec($curl);
    if (CheckHttpCodeChannel($curl))
    {
        $json2 = json_decode($resp);
        if (!isset($json2)) throw new Exception("bad playback-info json");
        $lang = StructArraySearch($json2->languages,"id","ru-RU",0);
        array_push($rends,(object)array("title" => $title, "rend" => $json2->languages[$lang]->renditions));
    }
   }
  }
  if ($mode=='split')
  {
    $playlist_basename = pathinfo($playlist_file, PATHINFO_DIRNAME);
    if ($playlist_basename=='.') $playlist_basename="";
    $playlist_basename .= pathinfo($playlist_file, PATHINFO_FILENAME);

    foreach($rends as $rend)
     foreach($rend->rend as $r)
      if ($r->id) $ids[strtolower($r->id)]=false;
    foreach ($ids as $i=>$v)
     $ids[$i]=create_m3u("$playlist_basename.$i.m3u");
    foreach($rends as $rend)
     foreach($rend->rend as $r)
      if ($r->id) write_m3u_chn($ids[strtolower($r->id)],$rend->title,$r->url);
  }
  else
  {
   $fplaylist = create_m3u($playlist_file);
   foreach($rends as $rend)
   {
    if ($mode=='all')
    {
     foreach ($rend->rend as $r)
      write_m3u_chn($fplaylist,$rend->title." (".strtolower($r->id).")",$r->url);
    }
    else if ($mode=='selectmax')
    {
     $rend_id = SearchMaxQualityId($rend->rend);
     if ($rend_id>=0) write_m3u_chn($fplaylist,$rend->title." (".$rend->rend[$rend_id]->id.")",$rend->rend[$rend_id]->url);
    }
    else
    {
     $rend_id = StructArraySearch($rend->rend,"id",$mode,-1);
     if ($rend_id>=0) write_m3u_chn($fplaylist,$rend->title,$rend->rend[$rend_id]->url);
    }
   }
  }

 }
 catch (Exception $e)
 {
  echo 'Exception: ',  $e->getMessage(), "\n";
  $err=1;
 }
 finally
 {
  curl_close($curl);
  if ($fplaylist) fclose($fplaylist);
  if ($ids)
   foreach($ids as $f)
    if ($f) fclose($f);
 }
 if ($err) die($err);
?>
