<?php
// bgloos001 alpha version 0.4
// by gofeel - gofeel@gmail.com
// http://bgloos.kldp.net

// 이 프로그램은 GNU General Public License Ver 2로 배포 됩니다.
// 라이센스에 대한 자세한 내용은 http://www.gnu.org/copyleft/gpl.html에 있습니다
// 이 프로그램은 태터 마이그레이터의 iconv함수를 사용하고 있습니다.
// http://www.tatterstory.net/ 에서 관련된 정보를 얻으실수 있습니다.



// --------------------절 취 선--------------------
// 이 아래는 php를 잘 모르시는 분이나
// 철이 덜 든 어른 및 어린이는 손대지 않는 것을 추천합니다.


//Main

$sFromEncoding="cp949";
if (!function_exists('iconv')) {
include "./iconv.php";
$sFromEncoding="EUC-KR";
}


switch ($_GET['action']) {
case "init":
	$iTemp=init();
	echo "<message>".$iTemp."</message>";
	break;
case "getpost":
	fProcess();
	break;
default:
	start_index();
}

exit(0);


function fProcess(){
	set_time_limit(0);
   global $bImageResize,$iResizeWidth,$sNick,$bImageDownload,$sFromEncoding;
	include ("bgloos_config.php");
	$pid=$_GET['postid'];
	$xxx=fopen ("./bgloos.sql","a");
	fwrite($xxx,fGetPost($sHost,$aPost[$pid],$pid+1,$sUserKey,$sBlogid,$aPostCategory));
	$pid++;
	header("Content-Type: text/xml;charset=ISO-8859-1");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	echo "<xml>";
	echo "<message>".$pid."</message>";
	echo "<percent>".sprintf("%.2f",$pid/count($aPost)*100)."</percent>";
	echo "<totalpost>".count($aPost)."</totalpost>";
	echo "</xml>";
	fclose($xxx);
	exit();
}


function fGetPost($sHost,$iPostNumber,$c,$sUserKey,$sBlogid,$aPostCategory)
{
	global $bImageResize,$iResizeWidth,$sNick,$bImageDownload,$sFromEncoding;
	$aTempCmt=fMakeCommentSql($sHost,$iPostNumber,$c,$sUserKey);

//Comment 처리
	$sComment="";
	if(is_array($aTempCmt))
	{
		foreach ($aTempCmt as $sTemp)
		{
			$sComment.=$sTemp;
		}
		$iCountCmt=count($aTempCmt);
	}
	else
	{
		$iCountCmt=0;
	}


//Trackback 처리
	$sTrackBack="";
	$iCountTb=0;
	$aTemptb=fMakeTrackbackSql($sHost,$iPostNumber,$c,$sUserKey);
	if(is_array($aTemptb)) {
		foreach ($aTemptb as $sTemp)
		{
			$sTrackBack.=$sTemp;
		}
		$iCountTb=count($aTemptb);
	}
	else {
		$iCountTb=0;
	}


//본문 처리

	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		echo "$errstr ($errno)<br>\n";
		}
	else {
		fputs ($fd, "GET /egloo/update.asp?eid=".$sBlogid."&srl=".$iPostNumber." HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
		}
	$buffer=1;
	$aMatch=array();
	$content="";
	$bOpen=1;
	$bComment=1;
	$bTrackBack=1;
	while ($buffer) {
		$buffer = fgets($fd, 65536);
		if (preg_match('/Edit\.content/',$buffer,$aMatch)) {
			$sContent=iconv($sFromEncoding,'utf-8',substr($buffer,17,-4));
		}
		if (preg_match('/Edit\.mcontent/',$buffer,$aMatch)) {
			$mcontent=iconv($sFromEncoding,'utf-8',substr($buffer,18,-4));
		}

		if (preg_match('/name=rdate/',$buffer,$aMatch)) {
			preg_match('/value=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$iTime=strtotime($aMatch[1]);
		}
		if (preg_match('/subject\\"/',$buffer,$aMatch)) {
			preg_match('/VALUE=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$sSubject=iconv($sFromEncoding,'utf-8',$aMatch[1]);
		}

		if (preg_match('/NAME=moresubject/',$buffer,$aMatch)) {
			preg_match('/VALUE=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$sMoreSubject=iconv($sFromEncoding,'utf-8',$aMatch[1]);
		}

		if (preg_match('/NAME=openflag/',$buffer,$aMatch)) {
			if (preg_match('/CHECKED/',$buffer)) { $bOpen=0; }
		}

		if (preg_match('/NAME=cmtflag/',$buffer,$aMatch)) {
			if (preg_match('/CHECKED/',$buffer)) { $bComment=0; }
		}

		if (preg_match('/NAME=trbflag/',$buffer,$aMatch)){
			if (preg_match('/CHECKED/',$buffer)) { $bTrackBack=0; }
			break;
		}
	}

	fclose ($fd);

	if (strlen($mcontent)!=0) {
		$sContent.="[#M_ ".$sMoreSubject." | 글 닫기 | \\r\\n".$mcontent."_M#]";
	}
	preg_match_all("/\\[#IMAGE\\|([^\\|]*)\\|([^\\|]*)\\|([^\\|]*)\\|([^\\|]*)\\|([^#|]*)(#\\]|\|[^#|]*#\\])/",$sContent,$aMatch);

	$aImageUrl=array();
	for($i=0;$i<count($aMatch[0]);$i++) {
		$tp=array($aMatch[0][$i], $aMatch[1][$i], $aMatch[2][$i], $aMatch[3][$i], $aMatch[4][$i], $aMatch[5][$i], $aMatch[6][$i]);
		if ($bImageResize && $tp[4]>$iResizeWidth) {
			$tp[5]=(int)($iResizeWidth*$tp[5]/$tp[4]);
			$tp[4]=$iResizeWidth;
		}
		$cPostion=strtoupper(substr($tp[3],0,1));
		$cPostion=($cPostion=="M"?"C":$cPostion);
		$tp[2]=str_replace("\\","",$tp[2]);
		if ($bImageDownload){
		$sContent=str_replace($tp[0],"[##_1".$cPostion."|".$tp[1]."|width=\"".$tp[4]."\" height=\"".$tp[5]."\"|_##]",$sContent);
		//http://pds.egloos.com/pds/1/200502/22/56/a0015856_0334.jpg
		$aImageUrl[]=array(0=>"http://".($tp[6]=="#]"?"pds":substr($tp[6],1,-2)).".egloos.com/pds/1/".$tp[2].$tp[1],1=>$tp[1]);
		}
		else
		{
		$sContent=str_replace($tp[0],"<div><img src=\"http://". ( $tp[6] == "#]" ? "pds":substr($tp[6],1,-2 ) ) . ".egloos.com/pds/1/".$tp[2].$tp[1]."\" align=\"".$tp[3]."\" width=\"".$tp[4]."\" height=\"".$tp[5]."\"></div>",$sContent);
		}
	}

	$sImageFilePath1=Date("md",$iTime);
	$sImageFilePath2=$sImageFilePath1.$iPostNumber;

//escape

   $sContent=escape_string($sContent);
   $sSubject=escape_string($sSubject);

	$rt="insert into t3_[##_dbid_##] (no, category1, category2, title, body, user_id, image_file_path1, image_file_path2, local_info, regdate, is_public, is_sync, rp_cnt, tb_cnt, perm_rp, perm_tb, subscription) values ('".$c."','".$aPostCategory[$iPostNumber]."', '0', '".$sSubject."','".$sContent."', '".$sNick."', '".$sImageFilePath1."/', '".$sImageFilePath2."/', '', '".$iTime."', '".$bOpen."', '0', '".$iCountCmt."', '".$iCountTb."', '".$bComment."', '".$bTrackBack."', '0')\n";
	if(count($aImageUrl)>0 && $bImageDownload)
		{
		$xxx=fopen ("./bgloos_at.php","r");
		$count=fgets($xxx,128);
		fclose($xxx);
		if(!file_exists("./attach/".$sImageFilePath1))
			{
			mkdir("./attach/".$sImageFilePath1,0777);
		}
		if(!file_exists("./attach/".$sImageFilePath1."/".$sImageFilePath2))
			{
			mkdir("./attach/".$sImageFilePath1."/".$sImageFilePath2,0777);
		}
		foreach ($aImageUrl As $tp)
			{
			$count++;
			$iLength= wget ($tp[0],"./attach/".$sImageFilePath1."/".$sImageFilePath2."/".$tp[1]);
			$rt.="insert into t3_[##_dbid_##]_files (no, pno, attachname, filename, filesize, width, height, regdate) values ('".$count."', '".$c."', '".$tp[1]."', '".$tp[1]."', '".$iLength."', '0', '0', '1142295873')\n";
		}
		$xxx=fopen ("./bgloos_at.php","w");
		fputs($xxx,$count);
		fclose($xxx);
	}
	return $rt.$sComment.$sTrackBack;
}



function fMakeCommentSql($sHost,$i,$postcount,$sUserKey)
	{
      global $sFromEncoding;
	$xxx=fopen ("./bgloos_cmt.php","r");
	$count=fgets($xxx,128);
	fclose($xxx);
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		echo $errstr." (".$errno.")<br>\n";
	} else {
		fputs ($fd, "GET /c".$i." HTTP/1.1\r\nHost: ".$sHost.".egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	while (!feof ($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/JavaScript/',$buffer,$aMatch)) break;
	}
	$buffer = fgets($fd, 4096);
	$c="";
	$c=fgett($fd);
	while (!feof ($fd)) {
		$c=fgett($fd);
		switch(substr($c,0,2))
			{
			case '<i':
			preg_match('/strong>([^<]*)</',$c,$aMatch);
			$name=iconv($sFromEncoding,"utf-8",$aMatch[1]);
			$secret=(preg_match('/security3.gif\\\"/',$c)?1:0);
			preg_match('/href=\\\\"([^"]*)\\\\" tit/',$c,$aMatch);
			$url=iconv($sFromEncoding,"utf-8",$aMatch[1]);
			$time=strtotime(substr($c,strpos($c,"/a> at ")+7,17));
			break;
			case '</':
			$text=preg_replace("/<br\/>/","\\r\\n",iconv($sFromEncoding,"utf-8",substr($c,138,-16)));
			if(preg_match('/^(.*)<\/div><\/div>/',$text,$aMatch)) {$text=$aMatch[1];} //마지막 코멘트 체크

         $name=escape_string($name);
         $text=escape_string($text);


			$rt[]="insert into t3_[##_dbid_##]_reply (no, pno, rno, sortno, name, homepage, body, is_root, password, is_secret, regdate, ip) values ('".$count."', '".$postcount."', '0', '0', '".$name."', '".$url."', '".$text."', '1', '49bf6be2050bf1d6', '".$secret."', '".$time."', '127.0.0.1')\n";
			$count++;
			break;
			default:
		}
		if (preg_match('/^<p style/',$c,$aMatch)) {break;}
	}
	fclose($fd);
	$xxx=fopen ("./bgloos_cmt.php","w");
	fputs($xxx,$count);
	fclose($xxx);
	return $rt;
}





function fMakeTrackbackSql($sHost,$i,$postcount,$sUserKey)
	{
      global $sFromEncoding;
	$xxx=fopen ("./bgloos_cmt.php","r");
	$count=fgets($xxx,128);
	fclose($xxx);
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		echo "$errstr ($errno)<br>\n";
	} else {
		fputs ($fd, "GET /t".$i." HTTP/1.1\r\nHost: ".$sHost.".egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	while (!feof ($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/JavaScript/',$buffer,$aMatch)) break;
	}
	$buffer = fgets($fd, 4096);
	$c="";
	while (!feof($fd)) {
		$buffer = fgett($fd);
		if (preg_match('/comment_line/i',$buffer,$aMatch)) break;
	}
	while($buffer&&!feof($fd))
		{
		$buffer = fgett($fd);
		if(preg_match('/\/\/-->/',$buffer)){break;}
		$buffer = fgett($fd);
		$buffer = fgett($fd);
		preg_match('/href=\\\\"([^"]*)\\\\"/',$buffer,$aMatch);
		$sUrl=$aMatch[1];
		preg_match('/strong>([^<]*)<\/strong>/',$buffer,$aMatch);
		$sBlogName=$aMatch[1];
		$buffer = fgett($fd);
		$iTime=strtotime(substr($buffer,3,18));
		$buffer = fgett($fd);
		$buffer = fgett($fd);
		$buffer = fgett($fd);
		$buffer = fgett($fd);
		preg_match('/\\\\">([^<]*)<\/a/',$buffer,$aMatch);
		$sTitle=$aMatch[1];
			$buffer = fgett($fd);
			$sContent=substr($buffer,0,-1*strpos($buffer,"<"));
			$buffer = fgett($fd);
			$buffer = fgett($fd);

			$sBlogName=iconv($sFromEncoding,"utf-8",$sBlogName);
			$sTitle=iconv($sFromEncoding,"utf-8",$sTitle);
			$sContent=iconv($sFromEncoding,"utf-8",$sContent);

	      $sContent=escape_string($sContent);
	      $sBlogName=escape_string($sBlogName);
	      $sTitle=escape_string($sTitle);


		$rt[]="insert into t3_[##_dbid_##]_trackback (no, pno, site, url, title, body, regdate, ip) values ('".$count."', '".$postcount."', '".$sBlogName."', '".$sUrl."', '".$sTitle."','".$sContent."', '".$iTime."', '127.0.0.1')\n";
			$count++;
		}
		fclose($fd);
		$xxx=fopen ("./bgloos_tb.php","w");
		fputs($xxx,$count);
		fclose($xxx);
		return $rt;
	}


function fgett($fd) {
		$rt="";
		while (!feof ($fd)) {
			$tmp=fgetc($fd);
			if( $tmp=="\t" or $tmp=="\n") { break; }
			$rt.=$tmp;
		}
		return $rt;
	}

function escape_string($sString) {
	$sString=str_replace("\'","'",$sString);
	$sString=str_replace("'","\'",$sString);
	return $sString;
}



function wget($url,$fp) {
	global $sUserKey;
	$sIpPds="211.239.119.179";
	$sIpPds1="211.239.119.167";
	$sIpPds2="211.239.119.164";
	$url = preg_replace("@^http://@i", "", $url);
	$sHost = substr($url, 0, strpos($url, "/"));
	$uri = strstr($url, "/");
	if($sHost=="pds.egloos.com") {
		$sIp=$sIpPds;
	}
	if($sHost=="pds1.egloos.com") {
		$sIp=$sIpPds1;
	}
	if($sHost=="pds2.egloos.com") {
		$sIp=$sIpPds2;
	}
	$fd = fsockopen ($sIp, 80, $errno, $errstr, 30);
	if (!$fd) {
		echo "$errstr ($errno)<br>\n";
		exit(0);
	} else {
		fputs ($fd, "GET ".$uri." HTTP/1.1\r\nHost: ".$sHost."\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	$xxx=fopen ($fp,"w");
	if (!$xxx) {
		echo "$errstr ($errno)<br>\n";
		exit(0);
	}
	while(!feof($fd)) {
		$buffer=fgets($fd);
		if(strpos($buffer,"Content-Length")!==false) {$iLength=substr($buffer,16);}
		if($buffer=="\r\n") {break;}
	}
	$iLength=(int)$iLength;
	for($i=0;$i<$iLength;$i++) {
		fwrite($xxx,fgetc($fd));
	}
	fclose($xxx);
	return $iLength;
}




function init(){
//	include "config.php";
global $sFromEncoding;
	set_time_limit(0);
	setlocale(LC_TIME, "C");
	header("Content-Type: text/xml;charset=ISO-8859-1");
	header("Cache-Control: no-store, no-cache, must-revalidate");

	$sEgloosId=$_GET['egloosid'];
	$sEgloosPass=$_GET['egloospass'];
	$sMysqlPass=$_GET['mysqlpass'];
   $bImageDownload=$_GET['id'];
	$bImageResize=$_GET['ir'];
   $iResizeWidth=$_GET['rw'];
	

	//기존의 파일 정리
	$xxx=fopen ("./bgloos_tb.php","w");
   if(!$xxx) {return 1;}
	fputs($xxx,1);
	fclose($xxx);
	$xxx=fopen ("./bgloos_cmt.php","w");
   if(!$xxx) {return 1;}
	fputs($xxx,1);
	fclose($xxx);
	$xxx=fopen ("./bgloos_at.php","w");
   if(!$xxx) {return 1;}
	fputs($xxx,1);
	fclose($xxx);

	//인증 키 및 블로그 hostname 

	$aTempPage=auth(array("userid" => $sEgloosId,"userpwd"=>$sEgloosPass,"userip"=>""));
	$sUserKey=substr($aTempPage[9],18,-30);
	preg_match('/http:\/\/([^\.]*).egloos.com/',$aTempPage[13],$aMatch);
	$sHost=$aMatch[1];
	if(!$sHost) {
		Return -1;
	}

	//sBlogid
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		return 2;
	} else {
		fputs ($fd, "GET /login/reset_skin.asp HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	$buffer="x";
	while ($buffer && !feof($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/NAME=eid value=([^>]*)>/',$buffer,$aMatch)) 
		{
			$sBlogid=$aMatch[1];
			break;
		}
	}
	fclose($fd);


	//글 관리 첫 페이지 다운로드
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		return 2;
	} else {
		fputs ($fd, "GET /adm/chgegloo_post.asp?eid=".$sBlogid."&pagecount=100 HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	$buffer="x";
	while ($buffer && !feof($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/JAVASCRIPT/',$buffer,$aMatch)) break;
	}
	//글 관리 첫 페이지 다운로드
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		return 2;
	} else {
		fputs ($fd, "GET /adm/chgegloo_post.asp?eid=".$sBlogid."&pagecount=100 HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	$buffer="x";
	while ($buffer && !feof($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/JAVASCRIPT/',$buffer,$aMatch)) break;
	}

	//글 관리 첫 페이지 다운로드
	$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
	if (!$fd) {
		return 2;
	} else {
		fputs ($fd, "GET /adm/chgegloo_post.asp?eid=".$sBlogid."&pagecount=100 HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
	}
	$buffer="x";
	while ($buffer && !feof($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/JAVASCRIPT/',$buffer,$aMatch)) break;
	}

	//Category 목록작성 
	fgets($fd, 4096);
	$buffer = fgets($fd, 4096);
	preg_match_all('/\\"([^,]*)\\"/',$buffer,$aMatch);
	$aCategoryName=$aMatch[1];
	$buffer = fgets($fd, 4096);
	preg_match_all('/\\"([^,]*)\\"/',$buffer,$aMatch);
	$aCategoryNumber=$aMatch[1];
	for($i=1;$i<count($aCategoryNumber);$i++) {
		$aCategory[$aCategoryNumber[$i]]=$aCategoryName[$i];
	}

	while ($buffer && !feof($fd)) {
		$buffer = fgets($fd, 4096);
		if (preg_match('/<TD WIDTH=570 CLASS=GRAY ALIGN=LEFT VALIGN=MIDDLE>/',$buffer,$aMatch)) {
			preg_match('/<B>([^<]*)<\/B>/',$buffer,$aMatch);
			$iTotalPost=(int)$aMatch[1];
			break;
		}
	}
	if (!$iTotalPost) {return 2;}

	$iLeftPost=$iTotalPost;
	$iCount=0;
	$iPage=1;

	while($iLeftPost>0) {

		$iTemp=($iLeftPost>100?100:$iLeftPost);
		while ($buffer) {
			$buffer = fgets($fd, 4096);
			if (preg_match('/<TD WIDTH=650 CLASS=BLACK>/',$buffer,$aMatch)) { break; }
		}

		for($i=$iCount;$i<$iTemp+$iCount&&!feof($fd);$i++) {
			fgets($fd, 4096);
			fgets($fd, 4096);
			fgets($fd, 4096);
			fgets($fd, 4096);
			fgets($fd, 4096);
			$buffer = fgets($fd, 4096);
			preg_match("/egloos\\.com\\/([0-9]*) TITLE/",$buffer,$aMatch);
			$aPost[$i]=$aMatch[1];
			fgets($fd, 4096);
			$buffer=fgets($fd, 4096);
			preg_match('/category=([0-9]*) /',$buffer,$aMatch);
			$aPostCategory[$aPost[$i]]=$aMatch[1];
			fgets($fd, 4096);
			fgets($fd, 4096);
		}
		fclose($fd);
		$iLeftPost-=$iTemp;
		$iCount+=$iTemp;
		$iPage++;
		if ($iLeftPost<=0) { break; }

		$fd = fsockopen ("211.239.119.245", 80, $errno, $errstr, 30);
		if (!$fd) {
			return $errno;
		} else {
			fputs ($fd, "GET /adm/chgegloo_post.asp?opt=2&serial=".$aPost[$i-1]."&eid=".$sBlogid."&pagecount=100&date=&category=&pg=".$iPage." HTTP/1.1\r\nHost: www.egloos.com\r\nCookie: check=1; editor=opt=1; u=key=".$sUserKey."\r\n\r\n");
		}
	}

	$oConfigFile=fopen ("./bgloos_config.php","w");
	if (!$oConfigFile) {
		return 1;
	}

	$aPost=array_reverse($aPost);
	fwrite($oConfigFile,"<?php\n");
	for($i=0;$i<$iTotalPost;$i++)
	{
		fwrite($oConfigFile,"\$aPost[".$i."]=".$aPost[$i].";\n");
		fwrite($oConfigFile,"\$aPostCategory[".$aPost[$i]."]=".$aPostCategory[$aPost[$i]].";\n");
	}
	fwrite($oConfigFile,"\$sUserKey=\"".$sUserKey."\";\n");
	fwrite($oConfigFile,"\$sHost=\"".$sHost."\";\n");
	fwrite($oConfigFile,"\$sNick=\"".$sNick."\";\n");
	fwrite($oConfigFile,"\$sBlogid=\"".$sBlogid."\";\n");
   fwrite($oConfigFile,"\$bImageDownload=".$bImageDownload.";\n");
   fwrite($oConfigFile,"\$bImageResize=".$bImageResize.";\n");
   fwrite($oConfigFile,"\$iResizeWidth=\"".$iResizeWidth."\";\n");
	fwrite($oConfigFile,"?>\n");
	fclose($oConfigFile);

	$xxx=fopen ("./bgloos.sql","w");
	foreach($aCategory as $key =>$tp)
	{
		fwrite($xxx,"insert into t3_[##_dbid_##]_ct1 (no, sortno, label, cnt) values ('".$key."', '".$c."', '".iconv($sFromEncoding,"utf-8",$tp)."', '0')\n");
      //echo iconv($sFromEncoding,"utf-8",$tp);
      //echo $tp;
	}
	fclose($xxx);
	return 0;
}






function auth($datastream) {
	$reqbody = "";
	foreach($datastream as $key=>$val) {
	if (!empty($reqbody)) $reqbody.= "&";
	   $reqbody.= $key."=".urlencode($val);
	}
	$contentlength = strlen($reqbody);
	$reqheader =  "POST /authid.asp HTTP/1.0\r\n".
	"Host: www.egloos.com\r\n".
	"Content-Type: application/x-www-form-urlencoded\r\n".
	"Content-Length: $contentlength\r\n\r\n".
	"$reqbody\r\n";
	$socket = fsockopen("211.239.119.245", 80, $errno, $errstr);
	if (!$socket)
	{
		$result["errno"] = $errno;
		$result["errstr"] = $errstr;
		return $result;
	}
	fputs($socket, $reqheader);
	$tmp="x";
	while ($tmp!="")
	{
		$tmp=$result[] = fgets($socket, 4096);
	}
	fclose($socket);
	return $result;
}






function start_index() {
	header("Content-Type: text/html;charset=UTF-8");
	header("Cache-Control: no-store, no-cache, must-revalidate");
?>
<html>
<head>
<script type="text/javascript">
			var isIE = false;
			var req;
			var messageHash = -1;
			var percentHash = -1;
			var totalpost = -1;
			var centerCell;
			var size=40;
			var increment = 100/size;
			function pollTaskmaster() {
				var url = "bgloos.php?action=getpost&postid=" + messageHash;
				initRequest(url);
				req.onreadystatechange = processPollRequest;
				req.send(null);
			}
			function processPollRequest() {
				if (req.readyState == 4) {
					if (req.status == 200) {
						var item = req.responseXML.getElementsByTagName("message")[0];
						var message = item.firstChild.nodeValue;
						item = req.responseXML.getElementsByTagName("percent")[0];
						percentHash = item.firstChild.nodeValue;
                  item = req.responseXML.getElementsByTagName("totalpost")[0];
						totalpost = item.firstChild.nodeValue;
						showProgress(percentHash);
						messageHash = message;
					} else {
						window.status = "No Update for " + targetId;
					}
					window.status = "총 " + totalpost + "개의 글 중에서 " + messageHash + "번째 글 처리중......";    
					var idiv = window.document.getElementById("debug");
				idiv.innerHTML = "Debug : <a href=\"bgloos.php?postid=" + messageHash + "\">bgloos.php?postid=" + messageHash + "</a>";
					if (percentHash < 100) {
						setTimeout("pollTaskmaster()", 50);
					} else {
						setTimeout("complete()", 25);
					}
				}
			}
			function initRequest(url) {
				if (window.XMLHttpRequest) {
					req = new XMLHttpRequest();
				} else if (window.ActiveXObject) {
					isIE = true;
					req = new ActiveXObject("Microsoft.XMLHTTP");
				}
				req.open("GET", url, true);
			}
			function submitTask() {
            var mysqlpass = window.document.getElementById("mysqlpass").value;
            var egloospass = window.document.getElementById("egloospass").value;
            var egloosid = window.document.getElementById("egloosid").value;
            var id = window.document.getElementById("id").checked;
            var ir = window.document.getElementById("ir").checked;
            var rw = window.document.getElementById("resizewidth").value;
				var idiv = window.document.getElementById("input_form");
				idiv.style.display = "none";
				var url = "bgloos.php?action=init&mysqlpass=" + mysqlpass + "&egloospass=" + egloospass + "&egloosid=" + egloosid + "&id=" + id + "&ir=" + ir+ "&rw=" + rw;
				var bttn = window.document.getElementById("taskbutton");
				initRequest(url);
				// set callback function
				req.onreadystatechange = processInitialRequest;
				req.send(null);
            return false;
			}
			function complete() {
				var idiv = window.document.getElementById("progress");
				idiv.innerHTML = "Mission Complete!<br/><a href=\"./admin/setting.php\">태터툴즈 관리자 데이터 복구 페이지</a>에서 http://<?php echo $_SERVER["SERVER_NAME"]?><?php echo substr($_SERVER["PHP_SELF"],0,-11);?>/bgloos.sql을 입력해서 복구하기 바람";
				window.status = "완료";
				var bttn = window.document.getElementById("taskbutton");
				bttn.disabled = false;
			}
			// callback function for intial request to schedule a task
			function processInitialRequest() {
				if (req.readyState == 4) {
					if (req.status == 200) {
						var item = req.responseXML.getElementsByTagName("message")[0];
						var message = item.firstChild.nodeValue;
						// the initial requests gets the targetId
						messageHash = 0;
						window.status = "";
                  createProgressBar();
						showProgress(0);
               }
     
						var idiv = window.document.getElementById("task_id");             
					if (message == 0) {

                  // do the initial poll in 2 seconds

						idiv.innerHTML = "초기 설정 완료. 백업을 시작합니다.";
                  setTimeout("pollTaskmaster()", 20);
					}
					if (message == -1) {
						idiv.innerHTML = "로그인 실패 아이디 패스워드 확인 바람";
                  return false;  
					}
               if (message == 1) {
						idiv.innerHTML = "파일 생성 실패 퍼미션 확인바람";
                  return false;  
					}
               if (message == 2) {
						idiv.innerHTML = "이글루스 접속 실패 잠시후 다시 시도 하거나 세팅확인";
                  return false;
					}

				}
}
			// create the progress bar
			function createProgressBar() {
				var centerCellName;
				var tableText = "";
				for (x = 0; x < size; x++) {
					tableText += "<td id=\"progress_" + x + "\" width=\"10\" height=\"10\" bgcolor=\"blue\"/>";
						if (x == (size/2)) {
							centerCellName = "progress_" + x;
						}
					}
					var idiv = window.document.getElementById("progress");
					idiv.innerHTML = "<table with=\"100\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr>" + tableText + "</tr></table>";
					centerCell = window.document.getElementById(centerCellName);
				}
				// show the current percentage
				function showProgress(percentage) {
					var percentageText = "";
					if (percentage < 10) {
						percentageText = "&nbsp;" + percentage;
					} else {
						percentageText = percentage;
					}
					centerCell.innerHTML = "<font color=\"white\">" + percentageText + "%</font>";
					var tableText = "";
					for (x = 0; x < size; x++) {
						var cell = window.document.getElementById("progress_" + x);
						if ((cell) && percentage/x < increment) {
							cell.style.backgroundColor = "blue";
						} else {
							cell.style.backgroundColor = "red";
						}      
					}
				}
</script>
<title>bgloos001</title>
</head>
<body>
<h1>bgloos 001</h1>
<hr/>
<p>
이글루-&gt;태터툴즈<br>알파 4
</p>
<div id="input_form">
<!-- 폼의 시작 -->
<form action="#" id=iform>
egloos ID : <input type="text" name="egloosid" id="egloosid"/><br/>
egloos PASSWORD :<input type="password" name="egloospass" id="egloospass"/><br/>
mysql db PASSWORD :<input type="password" name="mysqlpass" id="mysqlpass"/><br/>
<br/>
<br/>
옵션
<hr/>
<input id="id" type="checkbox" name="id" value="true" CHECKED/><label for=imagedownload>이미지 다운로드</label><br/>
<input id="ir" type="checkbox" name="ir" value="true" CHECKED/><label for=imageresize>이미지 리사이즈</label>
 (<input type="text" id="resizewidth" name="resizewidth" VALUE="500"/>px 이상을 리사이즈 합니다.)<br/>
닉네임 : <input type="text" name="nickname" value="Test" /><br/>
<input id="taskbutton" type="button" name="submittask" value="삽질 시작" onClick="submitTask()"/>
</form>
<!-- 폼의 끝 -->
</div>

<div id="task_id"></div><br/>
<div id="progress"></div>
<div id="debug"></div>
</body>
</html>
<?php
}
?>
