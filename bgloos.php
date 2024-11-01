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
   global $bImageResize,$iResizeWidth,$sNick,$bImageDownload;
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
	global $bImageResize,$iResizeWidth,$sNick,$bImageDownload;
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
			$sContent=iconv('cp949','utf8',substr($buffer,17,-4));
		}
		if (preg_match('/Edit\.mcontent/',$buffer,$aMatch)) {
			$mcontent=iconv('cp949','utf8',substr($buffer,18,-4));
		}

		if (preg_match('/name=rdate/',$buffer,$aMatch)) {
			preg_match('/value=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$iTime=strtotime($aMatch[1]);
		}
		if (preg_match('/subject\\"/',$buffer,$aMatch)) {
			preg_match('/VALUE=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$sSubject=iconv('cp949','utf8',$aMatch[1]);
		}

		if (preg_match('/NAME=moresubject/',$buffer,$aMatch)) {
			preg_match('/VALUE=\\"([^\\"]*)\\"/',$buffer,$aMatch);
			$sMoreSubject=iconv('cp949','utf8',$aMatch[1]);
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
			$name=iconv("cp949","utf8",$aMatch[1]);
			$secret=(preg_match('/security3.gif\\\"/',$c)?1:0);
			preg_match('/href=\\\\"([^"]*)\\\\" tit/',$c,$aMatch);
			$url=iconv("cp949","utf8",$aMatch[1]);
			$time=strtotime(substr($c,strpos($c,"/a> at ")+7,17));
			break;
			case '</':
			$text=preg_replace("/<br\/>/","\\r\\n",iconv("cp949","utf8",substr($c,138,-16)));
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

			$sBlogName=iconv("cp949","utf8",$sBlogName);
			$sTitle=iconv("cp949","utf8",$sTitle);
			$sContent=iconv("cp949","utf8",$sContent);

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
	include "config.php";
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
	
	if ($sMysqlPass!=$pass) {
		return -1;
	}

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
		fwrite($xxx,"insert into t3_[##_dbid_##]_ct1 (no, sortno, label, cnt) values ('".$key."', '".$c."', '".iconv("cp949","utf8",$tp)."', '0')\n");
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
if (!function_exists('iconv')) {
function iconv($from, $to, $str) {
	$from = strtoupper($from);
	$to = strtoupper($to);
	if ($to == 'UTF-8') {
		$m = array('EUC-KR' => array(array(0xA1A1,0xA1FE,'　、。·.‥…¨.〃­.―∥＼∼‘’“”〔〕〈〉《》「」『』【】±.×.÷.≠≤≥∞∴°.′″℃Å￠￡￥♂♀∠⊥⌒∂∇≡≒§.※☆★○●◎◇◆□■△▲▽▼→←↑↓↔〓≪≫√∽∝∵∫∬∈∋⊆⊇⊂⊃∪∩∧∨￢'),array(0xA2A1,0xA2E7,'⇒⇔∀∃´.～ˇ.˘.˝.˚.˙.¸.˛.¡.¿.ː.∮∑∏¤.℉‰◁◀▷▶♤♠♡♥♧♣⊙◈▣◐◑▒▤▥▨▧▦▩♨☏☎☜☞¶.†‡↕↗↙↖↘♭♩♪♬㉿㈜№㏇™㏂㏘℡€®.'),array(0xA3A1,0xA3FE,'！＂＃＄％＆＇（）＊＋，－．／０１２３４５６７８９：；＜＝＞？＠ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺ［￦］＾＿｀ａｂｃｄｅｆｇｈｉｊｋｌｍｎｏｐｑｒｓｔｕｖｗｘｙｚ｛｜｝￣'),array(0xA4A1,0xA4FE,'ㄱㄲㄳㄴㄵㄶㄷㄸㄹㄺㄻㄼㄽㄾㄿㅀㅁㅂㅃㅄㅅㅆㅇㅈㅉㅊㅋㅌㅍㅎㅏㅐㅑㅒㅓㅔㅕㅖㅗㅘㅙㅚㅛㅜㅝㅞㅟㅠㅡㅢㅣㅤㅥㅦㅧㅨㅩㅪㅫㅬㅭㅮㅯㅰㅱㅲㅳㅴㅵㅶㅷㅸㅹㅺㅻㅼㅽㅾㅿㆀㆁㆂㆃㆄㆅㆆㆇㆈㆉㆊㆋㆌㆍㆎ'),array(0xA5A1,0xA5AA,'ⅰⅱⅲⅳⅴⅵⅶⅷⅸⅹ'),array(0xA5B0,0xA5B9,'ⅠⅡⅢⅣⅤⅥⅦⅧⅨⅩ'),array(0xA5C1,0xA5D8,'Α.Β.Γ.Δ.Ε.Ζ.Η.Θ.Ι.Κ.Λ.Μ.Ν.Ξ.Ο.Π.Ρ.Σ.Τ.Υ.Φ.Χ.Ψ.Ω.'),array(0xA5E1,0xA5F8,'α.β.γ.δ.ε.ζ.η.θ.ι.κ.λ.μ.ν.ξ.ο.π.ρ.σ.τ.υ.φ.χ.ψ.ω.'),array(0xA6A1,0xA6E4,'─│┌┐┘└├┬┤┴┼━┃┏┓┛┗┣┳┫┻╋┠┯┨┷┿┝┰┥┸╂┒┑┚┙┖┕┎┍┞┟┡┢┦┧┩┪┭┮┱┲┵┶┹┺┽┾╀╁╃╄╅╆╇╈╉╊'),array(0xA7A1,0xA7EF,'㎕㎖㎗ℓ㎘㏄㎣㎤㎥㎦㎙㎚㎛㎜㎝㎞㎟㎠㎡㎢㏊㎍㎎㎏㏏㎈㎉㏈㎧㎨㎰㎱㎲㎳㎴㎵㎶㎷㎸㎹㎀㎁㎂㎃㎄㎺㎻㎼㎽㎾㎿㎐㎑㎒㎓㎔Ω㏀㏁㎊㎋㎌㏖㏅㎭㎮㎯㏛㎩㎪㎫㎬㏝㏐㏓㏃㏉㏜㏆'),array(0xA8A1,0xA8A4,'Æ.Ð.ª.Ħ.'),array(0xA8A6,0xA8A6,'Ĳ.'),array(0xA8A8,0xA8AF,'Ŀ.Ł.Ø.Œ.º.Þ.Ŧ.Ŋ.'),array(0xA8B1,0xA8FE,'㉠㉡㉢㉣㉤㉥㉦㉧㉨㉩㉪㉫㉬㉭㉮㉯㉰㉱㉲㉳㉴㉵㉶㉷㉸㉹㉺㉻ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨⓩ①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮½.⅓⅔¼.¾.⅛⅜⅝⅞'),array(0xA9A1,0xA9FE,'æ.đ.ð.ħ.ı.ĳ.ĸ.ŀ.ł.ø.œ.ß.þ.ŧ.ŋ.ŉ.㈀㈁㈂㈃㈄㈅㈆㈇㈈㈉㈊㈋㈌㈍㈎㈏㈐㈑㈒㈓㈔㈕㈖㈗㈘㈙㈚㈛⒜⒝⒞⒟⒠⒡⒢⒣⒤⒥⒦⒧⒨⒩⒪⒫⒬⒭⒮⒯⒰⒱⒲⒳⒴⒵⑴⑵⑶⑷⑸⑹⑺⑻⑼⑽⑾⑿⒀⒁⒂¹.².³.⁴ⁿ₁₂₃₄'),array(0xAAA1,0xAAF3,'ぁあぃいぅうぇえぉおかがきぎくぐけげこごさざしじすずせぜそぞただちぢっつづてでとどなにぬねのはばぱひびぴふぶぷへべぺほぼぽまみむめもゃやゅゆょよらりるれろゎわゐゑをん'),array(0xABA1,0xABF6,'ァアィイゥウェエォオカガキギクグケゲコゴサザシジスズセゼソゾタダチヂッツヅテデトドナニヌネノハバパヒビピフブプヘベペホボポマミムメモャヤュユョヨラリルレロヮワヰヱヲンヴヵヶ'),array(0xACA1,0xACC1,'А.Б.В.Г.Д.Е.Ё.Ж.З.И.Й.К.Л.М.Н.О.П.Р.С.Т.У.Ф.Х.Ц.Ч.Ш.Щ.Ъ.Ы.Ь.Э.Ю.Я.'),array(0xACD1,0xACF1,'а.б.в.г.д.е.ё.ж.з.и.й.к.л.м.н.о.п.р.с.т.у.ф.х.ц.ч.ш.щ.ъ.ы.ь.э.ю.я.'),array(0xB0A1,0xB0FE,'가각간갇갈갉갊감갑값갓갔강갖갗같갚갛개객갠갤갬갭갯갰갱갸갹갼걀걋걍걔걘걜거걱건걷걸걺검겁것겄겅겆겉겊겋게겐겔겜겝겟겠겡겨격겪견겯결겸겹겻겼경곁계곈곌곕곗고곡곤곧골곪곬곯곰곱곳공곶과곽관괄괆'),array(0xB1A1,0xB1FE,'괌괍괏광괘괜괠괩괬괭괴괵괸괼굄굅굇굉교굔굘굡굣구국군굳굴굵굶굻굼굽굿궁궂궈궉권궐궜궝궤궷귀귁귄귈귐귑귓규균귤그극근귿글긁금급긋긍긔기긱긴긷길긺김깁깃깅깆깊까깍깎깐깔깖깜깝깟깠깡깥깨깩깬깰깸'),array(0xB2A1,0xB2FE,'깹깻깼깽꺄꺅꺌꺼꺽꺾껀껄껌껍껏껐껑께껙껜껨껫껭껴껸껼꼇꼈꼍꼐꼬꼭꼰꼲꼴꼼꼽꼿꽁꽂꽃꽈꽉꽐꽜꽝꽤꽥꽹꾀꾄꾈꾐꾑꾕꾜꾸꾹꾼꿀꿇꿈꿉꿋꿍꿎꿔꿜꿨꿩꿰꿱꿴꿸뀀뀁뀄뀌뀐뀔뀜뀝뀨끄끅끈끊끌끎끓끔끕끗끙'),array(0xB3A1,0xB3FE,'끝끼끽낀낄낌낍낏낑나낙낚난낟날낡낢남납낫났낭낮낯낱낳내낵낸낼냄냅냇냈냉냐냑냔냘냠냥너넉넋넌널넒넓넘넙넛넜넝넣네넥넨넬넴넵넷넸넹녀녁년녈념녑녔녕녘녜녠노녹논놀놂놈놉놋농높놓놔놘놜놨뇌뇐뇔뇜뇝'),array(0xB4A1,0xB4FE,'뇟뇨뇩뇬뇰뇹뇻뇽누눅눈눋눌눔눕눗눙눠눴눼뉘뉜뉠뉨뉩뉴뉵뉼늄늅늉느늑는늘늙늚늠늡늣능늦늪늬늰늴니닉닌닐닒님닙닛닝닢다닥닦단닫달닭닮닯닳담답닷닸당닺닻닿대댁댄댈댐댑댓댔댕댜더덕덖던덛덜덞덟덤덥'),array(0xB5A1,0xB5FE,'덧덩덫덮데덱덴델뎀뎁뎃뎄뎅뎌뎐뎔뎠뎡뎨뎬도독돈돋돌돎돐돔돕돗동돛돝돠돤돨돼됐되된될됨됩됫됴두둑둔둘둠둡둣둥둬뒀뒈뒝뒤뒨뒬뒵뒷뒹듀듄듈듐듕드득든듣들듦듬듭듯등듸디딕딘딛딜딤딥딧딨딩딪따딱딴딸'),array(0xB6A1,0xB6FE,'땀땁땃땄땅땋때땍땐땔땜땝땟땠땡떠떡떤떨떪떫떰떱떳떴떵떻떼떽뗀뗄뗌뗍뗏뗐뗑뗘뗬또똑똔똘똥똬똴뙈뙤뙨뚜뚝뚠뚤뚫뚬뚱뛔뛰뛴뛸뜀뜁뜅뜨뜩뜬뜯뜰뜸뜹뜻띄띈띌띔띕띠띤띨띰띱띳띵라락란랄람랍랏랐랑랒랖랗'),array(0xB7A1,0xB7FE,'래랙랜랠램랩랫랬랭랴략랸럇량러럭런럴럼럽럿렀렁렇레렉렌렐렘렙렛렝려력련렬렴렵렷렸령례롄롑롓로록론롤롬롭롯롱롸롼뢍뢨뢰뢴뢸룀룁룃룅료룐룔룝룟룡루룩룬룰룸룹룻룽뤄뤘뤠뤼뤽륀륄륌륏륑류륙륜률륨륩'),array(0xB8A1,0xB8FE,'륫륭르륵른를름릅릇릉릊릍릎리릭린릴림립릿링마막만많맏말맑맒맘맙맛망맞맡맣매맥맨맬맴맵맷맸맹맺먀먁먈먕머먹먼멀멂멈멉멋멍멎멓메멕멘멜멤멥멧멨멩며멱면멸몃몄명몇몌모목몫몬몰몲몸몹못몽뫄뫈뫘뫙뫼'),array(0xB9A1,0xB9FE,'묀묄묍묏묑묘묜묠묩묫무묵묶문묻물묽묾뭄뭅뭇뭉뭍뭏뭐뭔뭘뭡뭣뭬뮈뮌뮐뮤뮨뮬뮴뮷므믄믈믐믓미믹민믿밀밂밈밉밋밌밍및밑바박밖밗반받발밝밞밟밤밥밧방밭배백밴밸뱀뱁뱃뱄뱅뱉뱌뱍뱐뱝버벅번벋벌벎범법벗'),array(0xBAA1,0xBAFE,'벙벚베벡벤벧벨벰벱벳벴벵벼벽변별볍볏볐병볕볘볜보복볶본볼봄봅봇봉봐봔봤봬뵀뵈뵉뵌뵐뵘뵙뵤뵨부북분붇불붉붊붐붑붓붕붙붚붜붤붰붸뷔뷕뷘뷜뷩뷰뷴뷸븀븃븅브븍븐블븜븝븟비빅빈빌빎빔빕빗빙빚빛빠빡빤'),array(0xBBA1,0xBBFE,'빨빪빰빱빳빴빵빻빼빽뺀뺄뺌뺍뺏뺐뺑뺘뺙뺨뻐뻑뻔뻗뻘뻠뻣뻤뻥뻬뼁뼈뼉뼘뼙뼛뼜뼝뽀뽁뽄뽈뽐뽑뽕뾔뾰뿅뿌뿍뿐뿔뿜뿟뿡쀼쁑쁘쁜쁠쁨쁩삐삑삔삘삠삡삣삥사삭삯산삳살삵삶삼삽삿샀상샅새색샌샐샘샙샛샜생샤'),array(0xBCA1,0xBCFE,'샥샨샬샴샵샷샹섀섄섈섐섕서석섞섟선섣설섦섧섬섭섯섰성섶세섹센셀셈셉셋셌셍셔셕션셜셤셥셧셨셩셰셴셸솅소속솎손솔솖솜솝솟송솥솨솩솬솰솽쇄쇈쇌쇔쇗쇘쇠쇤쇨쇰쇱쇳쇼쇽숀숄숌숍숏숑수숙순숟술숨숩숫숭'),array(0xBDA1,0xBDFE,'숯숱숲숴쉈쉐쉑쉔쉘쉠쉥쉬쉭쉰쉴쉼쉽쉿슁슈슉슐슘슛슝스슥슨슬슭슴습슷승시식신싣실싫심십싯싱싶싸싹싻싼쌀쌈쌉쌌쌍쌓쌔쌕쌘쌜쌤쌥쌨쌩썅써썩썬썰썲썸썹썼썽쎄쎈쎌쏀쏘쏙쏜쏟쏠쏢쏨쏩쏭쏴쏵쏸쐈쐐쐤쐬쐰'),array(0xBEA1,0xBEFE,'쐴쐼쐽쑈쑤쑥쑨쑬쑴쑵쑹쒀쒔쒜쒸쒼쓩쓰쓱쓴쓸쓺쓿씀씁씌씐씔씜씨씩씬씰씸씹씻씽아악안앉않알앍앎앓암압앗았앙앝앞애액앤앨앰앱앳앴앵야약얀얄얇얌얍얏양얕얗얘얜얠얩어억언얹얻얼얽얾엄업없엇었엉엊엌엎'),array(0xBFA1,0xBFFE,'에엑엔엘엠엡엣엥여역엮연열엶엷염엽엾엿였영옅옆옇예옌옐옘옙옛옜오옥온올옭옮옰옳옴옵옷옹옻와왁완왈왐왑왓왔왕왜왝왠왬왯왱외왹왼욀욈욉욋욍요욕욘욜욤욥욧용우욱운울욹욺움웁웃웅워웍원월웜웝웠웡웨'),array(0xC0A1,0xC0FE,'웩웬웰웸웹웽위윅윈윌윔윕윗윙유육윤율윰윱윳융윷으윽은을읊음읍읏응읒읓읔읕읖읗의읜읠읨읫이익인일읽읾잃임입잇있잉잊잎자작잔잖잗잘잚잠잡잣잤장잦재잭잰잴잼잽잿쟀쟁쟈쟉쟌쟎쟐쟘쟝쟤쟨쟬저적전절젊'),array(0xC1A1,0xC1FE,'점접젓정젖제젝젠젤젬젭젯젱져젼졀졈졉졌졍졔조족존졸졺좀좁좃종좆좇좋좌좍좔좝좟좡좨좼좽죄죈죌죔죕죗죙죠죡죤죵주죽준줄줅줆줌줍줏중줘줬줴쥐쥑쥔쥘쥠쥡쥣쥬쥰쥴쥼즈즉즌즐즘즙즛증지직진짇질짊짐집짓'),array(0xC2A1,0xC2FE,'징짖짙짚짜짝짠짢짤짧짬짭짯짰짱째짹짼쨀쨈쨉쨋쨌쨍쨔쨘쨩쩌쩍쩐쩔쩜쩝쩟쩠쩡쩨쩽쪄쪘쪼쪽쫀쫄쫌쫍쫏쫑쫓쫘쫙쫠쫬쫴쬈쬐쬔쬘쬠쬡쭁쭈쭉쭌쭐쭘쭙쭝쭤쭸쭹쮜쮸쯔쯤쯧쯩찌찍찐찔찜찝찡찢찧차착찬찮찰참찹찻'),array(0xC3A1,0xC3FE,'찼창찾채책챈챌챔챕챗챘챙챠챤챦챨챰챵처척천철첨첩첫첬청체첵첸첼쳄쳅쳇쳉쳐쳔쳤쳬쳰촁초촉촌촐촘촙촛총촤촨촬촹최쵠쵤쵬쵭쵯쵱쵸춈추축춘출춤춥춧충춰췄췌췐취췬췰췸췹췻췽츄츈츌츔츙츠측츤츨츰츱츳층'),array(0xC4A1,0xC4FE,'치칙친칟칠칡침칩칫칭카칵칸칼캄캅캇캉캐캑캔캘캠캡캣캤캥캬캭컁커컥컨컫컬컴컵컷컸컹케켁켄켈켐켑켓켕켜켠켤켬켭켯켰켱켸코콕콘콜콤콥콧콩콰콱콴콸쾀쾅쾌쾡쾨쾰쿄쿠쿡쿤쿨쿰쿱쿳쿵쿼퀀퀄퀑퀘퀭퀴퀵퀸퀼'),array(0xC5A1,0xC5FE,'큄큅큇큉큐큔큘큠크큭큰클큼큽킁키킥킨킬킴킵킷킹타탁탄탈탉탐탑탓탔탕태택탠탤탬탭탯탰탱탸턍터턱턴털턺텀텁텃텄텅테텍텐텔템텝텟텡텨텬텼톄톈토톡톤톨톰톱톳통톺톼퇀퇘퇴퇸툇툉툐투툭툰툴툼툽툿퉁퉈퉜'),array(0xC6A1,0xC6FE,'퉤튀튁튄튈튐튑튕튜튠튤튬튱트특튼튿틀틂틈틉틋틔틘틜틤틥티틱틴틸팀팁팃팅파팍팎판팔팖팜팝팟팠팡팥패팩팬팰팸팹팻팼팽퍄퍅퍼퍽펀펄펌펍펏펐펑페펙펜펠펨펩펫펭펴편펼폄폅폈평폐폘폡폣포폭폰폴폼폽폿퐁'),array(0xC7A1,0xC7FE,'퐈퐝푀푄표푠푤푭푯푸푹푼푿풀풂품풉풋풍풔풩퓌퓐퓔퓜퓟퓨퓬퓰퓸퓻퓽프픈플픔픕픗피픽핀필핌핍핏핑하학한할핥함합핫항해핵핸핼햄햅햇했행햐향허헉헌헐헒험헙헛헝헤헥헨헬헴헵헷헹혀혁현혈혐협혓혔형혜혠'),array(0xC8A1,0xC8FE,'혤혭호혹혼홀홅홈홉홋홍홑화확환활홧황홰홱홴횃횅회획횐횔횝횟횡효횬횰횹횻후훅훈훌훑훔훗훙훠훤훨훰훵훼훽휀휄휑휘휙휜휠휨휩휫휭휴휵휸휼흄흇흉흐흑흔흖흗흘흙흠흡흣흥흩희흰흴흼흽힁히힉힌힐힘힙힛힝'),array(0xCAA1,0xCAFE,'伽佳假價加可呵哥嘉嫁家暇架枷柯歌珂痂稼苛茄街袈訶賈跏軻迦駕刻却各恪慤殼珏脚覺角閣侃刊墾奸姦干幹懇揀杆柬桿澗癎看磵稈竿簡肝艮艱諫間乫喝曷渴碣竭葛褐蝎鞨勘坎堪嵌感憾戡敢柑橄減甘疳監瞰紺邯鑑鑒龕'),array(0xCBA1,0xCBFE,'匣岬甲胛鉀閘剛堈姜岡崗康强彊慷江畺疆糠絳綱羌腔舡薑襁講鋼降鱇介价個凱塏愷愾慨改槪漑疥皆盖箇芥蓋豈鎧開喀客坑更粳羹醵倨去居巨拒据據擧渠炬祛距踞車遽鉅鋸乾件健巾建愆楗腱虔蹇鍵騫乞傑杰桀儉劍劒檢'),array(0xCCA1,0xCCFE,'瞼鈐黔劫怯迲偈憩揭擊格檄激膈覡隔堅牽犬甄絹繭肩見譴遣鵑抉決潔結缺訣兼慊箝謙鉗鎌京俓倞傾儆勁勍卿坰境庚徑慶憬擎敬景暻更梗涇炅烱璟璥瓊痙硬磬竟競絅經耕耿脛莖警輕逕鏡頃頸驚鯨係啓堺契季屆悸戒桂械'),array(0xCDA1,0xCDFE,'棨溪界癸磎稽系繫繼計誡谿階鷄古叩告呱固姑孤尻庫拷攷故敲暠枯槁沽痼皐睾稿羔考股膏苦苽菰藁蠱袴誥賈辜錮雇顧高鼓哭斛曲梏穀谷鵠困坤崑昆梱棍滾琨袞鯤汨滑骨供公共功孔工恐恭拱控攻珙空蚣貢鞏串寡戈果瓜'),array(0xCEA1,0xCEFE,'科菓誇課跨過鍋顆廓槨藿郭串冠官寬慣棺款灌琯瓘管罐菅觀貫關館刮恝括适侊光匡壙廣曠洸炚狂珖筐胱鑛卦掛罫乖傀塊壞怪愧拐槐魁宏紘肱轟交僑咬喬嬌嶠巧攪敎校橋狡皎矯絞翹膠蕎蛟較轎郊餃驕鮫丘久九仇俱具勾'),array(0xCFA1,0xCFFE,'區口句咎嘔坵垢寇嶇廐懼拘救枸柩構歐毆毬求溝灸狗玖球瞿矩究絿耉臼舅舊苟衢謳購軀逑邱鉤銶駒驅鳩鷗龜國局菊鞠鞫麴君窘群裙軍郡堀屈掘窟宮弓穹窮芎躬倦券勸卷圈拳捲權淃眷厥獗蕨蹶闕机櫃潰詭軌饋句晷歸貴'),array(0xD0A1,0xD0FE,'鬼龜叫圭奎揆槻珪硅窺竅糾葵規赳逵閨勻均畇筠菌鈞龜橘克剋劇戟棘極隙僅劤勤懃斤根槿瑾筋芹菫覲謹近饉契今妗擒昑檎琴禁禽芩衾衿襟金錦伋及急扱汲級給亘兢矜肯企伎其冀嗜器圻基埼夔奇妓寄岐崎己幾忌技旗旣'),array(0xD1A1,0xD1FE,'朞期杞棋棄機欺氣汽沂淇玘琦琪璂璣畸畿碁磯祁祇祈祺箕紀綺羈耆耭肌記譏豈起錡錤飢饑騎騏驥麒緊佶吉拮桔金喫儺喇奈娜懦懶拏拿癩羅蘿螺裸邏那樂洛烙珞落諾酪駱亂卵暖欄煖爛蘭難鸞捏捺南嵐枏楠湳濫男藍襤拉'),array(0xD2A1,0xD2FE,'納臘蠟衲囊娘廊朗浪狼郎乃來內奈柰耐冷女年撚秊念恬拈捻寧寗努勞奴弩怒擄櫓爐瑙盧老蘆虜路露駑魯鷺碌祿綠菉錄鹿論壟弄濃籠聾膿農惱牢磊腦賂雷尿壘屢樓淚漏累縷陋嫩訥杻紐勒肋凜凌稜綾能菱陵尼泥匿溺多茶'),array(0xD3A1,0xD3FE,'丹亶但單團壇彖斷旦檀段湍短端簞緞蛋袒鄲鍛撻澾獺疸達啖坍憺擔曇淡湛潭澹痰聃膽蕁覃談譚錟沓畓答踏遝唐堂塘幢戇撞棠當糖螳黨代垈坮大對岱帶待戴擡玳臺袋貸隊黛宅德悳倒刀到圖堵塗導屠島嶋度徒悼挑掉搗桃'),array(0xD4A1,0xD4FE,'棹櫂淘渡滔濤燾盜睹禱稻萄覩賭跳蹈逃途道都鍍陶韜毒瀆牘犢獨督禿篤纛讀墩惇敦旽暾沌焞燉豚頓乭突仝冬凍動同憧東桐棟洞潼疼瞳童胴董銅兜斗杜枓痘竇荳讀豆逗頭屯臀芚遁遯鈍得嶝橙燈登等藤謄鄧騰喇懶拏癩羅'),array(0xD5A1,0xD5FE,'蘿螺裸邏樂洛烙珞絡落諾酪駱丹亂卵欄欒瀾爛蘭鸞剌辣嵐擥攬欖濫籃纜藍襤覽拉臘蠟廊朗浪狼琅瑯螂郞來崍徠萊冷掠略亮倆兩凉梁樑粮粱糧良諒輛量侶儷勵呂廬慮戾旅櫚濾礪藜蠣閭驢驪麗黎力曆歷瀝礫轢靂憐戀攣漣'),array(0xD6A1,0xD6FE,'煉璉練聯蓮輦連鍊冽列劣洌烈裂廉斂殮濂簾獵令伶囹寧岺嶺怜玲笭羚翎聆逞鈴零靈領齡例澧禮醴隷勞怒撈擄櫓潞瀘爐盧老蘆虜路輅露魯鷺鹵碌祿綠菉錄鹿麓論壟弄朧瀧瓏籠聾儡瀨牢磊賂賚賴雷了僚寮廖料燎療瞭聊蓼'),array(0xD7A1,0xD7FE,'遼鬧龍壘婁屢樓淚漏瘻累縷蔞褸鏤陋劉旒柳榴流溜瀏琉瑠留瘤硫謬類六戮陸侖倫崙淪綸輪律慄栗率隆勒肋凜凌楞稜綾菱陵俚利厘吏唎履悧李梨浬犁狸理璃異痢籬罹羸莉裏裡里釐離鯉吝潾燐璘藺躪隣鱗麟林淋琳臨霖砬'),array(0xD8A1,0xD8FE,'立笠粒摩瑪痲碼磨馬魔麻寞幕漠膜莫邈万卍娩巒彎慢挽晩曼滿漫灣瞞萬蔓蠻輓饅鰻唜抹末沫茉襪靺亡妄忘忙望網罔芒茫莽輞邙埋妹媒寐昧枚梅每煤罵買賣邁魅脈貊陌驀麥孟氓猛盲盟萌冪覓免冕勉棉沔眄眠綿緬面麵滅'),array(0xD9A1,0xD9FE,'蔑冥名命明暝椧溟皿瞑茗蓂螟酩銘鳴袂侮冒募姆帽慕摸摹暮某模母毛牟牡瑁眸矛耗芼茅謀謨貌木沐牧目睦穆鶩歿沒夢朦蒙卯墓妙廟描昴杳渺猫竗苗錨務巫憮懋戊拇撫无楙武毋無珷畝繆舞茂蕪誣貿霧鵡墨默們刎吻問文'),array(0xDAA1,0xDAFE,'汶紊紋聞蚊門雯勿沕物味媚尾嵋彌微未梶楣渼湄眉米美薇謎迷靡黴岷悶愍憫敏旻旼民泯玟珉緡閔密蜜謐剝博拍搏撲朴樸泊珀璞箔粕縛膊舶薄迫雹駁伴半反叛拌搬攀斑槃泮潘班畔瘢盤盼磐磻礬絆般蟠返頒飯勃拔撥渤潑'),array(0xDBA1,0xDBFE,'發跋醱鉢髮魃倣傍坊妨尨幇彷房放方旁昉枋榜滂磅紡肪膀舫芳蒡蚌訪謗邦防龐倍俳北培徘拜排杯湃焙盃背胚裴裵褙賠輩配陪伯佰帛柏栢白百魄幡樊煩燔番磻繁蕃藩飜伐筏罰閥凡帆梵氾汎泛犯範范法琺僻劈壁擘檗璧癖'),array(0xDCA1,0xDCFE,'碧蘗闢霹便卞弁變辨辯邊別瞥鱉鼈丙倂兵屛幷昞昺柄棅炳甁病秉竝輧餠騈保堡報寶普步洑湺潽珤甫菩補褓譜輔伏僕匐卜宓復服福腹茯蔔複覆輹輻馥鰒本乶俸奉封峯峰捧棒烽熢琫縫蓬蜂逢鋒鳳不付俯傅剖副否咐埠夫婦'),array(0xDDA1,0xDDFE,'孚孵富府復扶敷斧浮溥父符簿缶腐腑膚艀芙莩訃負賦賻赴趺部釜阜附駙鳧北分吩噴墳奔奮忿憤扮昐汾焚盆粉糞紛芬賁雰不佛弗彿拂崩朋棚硼繃鵬丕備匕匪卑妃婢庇悲憊扉批斐枇榧比毖毗毘沸泌琵痺砒碑秕秘粃緋翡肥'),array(0xDEA1,0xDEFE,'脾臂菲蜚裨誹譬費鄙非飛鼻嚬嬪彬斌檳殯浜濱瀕牝玭貧賓頻憑氷聘騁乍事些仕伺似使俟僿史司唆嗣四士奢娑寫寺射巳師徙思捨斜斯柶査梭死沙泗渣瀉獅砂社祀祠私篩紗絲肆舍莎蓑蛇裟詐詞謝賜赦辭邪飼駟麝削數朔索'),array(0xDFA1,0xDFFE,'傘刪山散汕珊産疝算蒜酸霰乷撒殺煞薩三參杉森渗芟蔘衫揷澁鈒颯上傷像償商喪嘗孀尙峠常床庠廂想桑橡湘爽牀狀相祥箱翔裳觴詳象賞霜塞璽賽嗇塞穡索色牲生甥省笙墅壻嶼序庶徐恕抒捿敍暑曙書栖棲犀瑞筮絮緖署'),array(0xE0A1,0xE0FE,'胥舒薯西誓逝鋤黍鼠夕奭席惜昔晳析汐淅潟石碩蓆釋錫仙僊先善嬋宣扇敾旋渲煽琁瑄璇璿癬禪線繕羨腺膳船蘚蟬詵跣選銑鐥饍鮮卨屑楔泄洩渫舌薛褻設說雪齧剡暹殲纖蟾贍閃陝攝涉燮葉城姓宬性惺成星晟猩珹盛省筬'),array(0xE1A1,0xE1FE,'聖聲腥誠醒世勢歲洗稅笹細說貰召嘯塑宵小少巢所掃搔昭梳沼消溯瀟炤燒甦疏疎瘙笑篠簫素紹蔬蕭蘇訴逍遡邵銷韶騷俗屬束涑粟續謖贖速孫巽損蓀遜飡率宋悚松淞訟誦送頌刷殺灑碎鎖衰釗修受嗽囚垂壽嫂守岫峀帥愁'),array(0xE2A1,0xE2FE,'戍手授搜收數樹殊水洙漱燧狩獸琇璲瘦睡秀穗竪粹綏綬繡羞脩茱蒐蓚藪袖誰讐輸遂邃酬銖銹隋隧隨雖需須首髓鬚叔塾夙孰宿淑潚熟琡璹肅菽巡徇循恂旬栒楯橓殉洵淳珣盾瞬筍純脣舜荀蓴蕣詢諄醇錞順馴戌術述鉥崇崧'),array(0xE3A1,0xE3FE,'嵩瑟膝蝨濕拾習褶襲丞乘僧勝升承昇繩蠅陞侍匙嘶始媤尸屎屍市弑恃施是時枾柴猜矢示翅蒔蓍視試詩諡豕豺埴寔式息拭植殖湜熄篒蝕識軾食飾伸侁信呻娠宸愼新晨燼申神紳腎臣莘薪藎蜃訊身辛辰迅失室實悉審尋心沁'),array(0xE4A1,0xE4FE,'沈深瀋甚芯諶什十拾雙氏亞俄兒啞娥峨我牙芽莪蛾衙訝阿雅餓鴉鵝堊岳嶽幄惡愕握樂渥鄂鍔顎鰐齷安岸按晏案眼雁鞍顔鮟斡謁軋閼唵岩巖庵暗癌菴闇壓押狎鴨仰央怏昻殃秧鴦厓哀埃崖愛曖涯碍艾隘靄厄扼掖液縊腋額'),array(0xE5A1,0xE5FE,'櫻罌鶯鸚也倻冶夜惹揶椰爺耶若野弱掠略約若葯蒻藥躍亮佯兩凉壤孃恙揚攘敭暘梁楊樣洋瀁煬痒瘍禳穰糧羊良襄諒讓釀陽量養圄御於漁瘀禦語馭魚齬億憶抑檍臆偃堰彦焉言諺孼蘖俺儼嚴奄掩淹嶪業円予余勵呂女如廬'),array(0xE6A1,0xE6FE,'旅歟汝濾璵礖礪與艅茹輿轝閭餘驪麗黎亦力域役易曆歷疫繹譯轢逆驛嚥堧姸娟宴年延憐戀捐挻撚椽沇沿涎涓淵演漣烟然煙煉燃燕璉硏硯秊筵緣練縯聯衍軟輦蓮連鉛鍊鳶列劣咽悅涅烈熱裂說閱厭廉念捻染殮炎焰琰艶苒'),array(0xE7A1,0xE7FE,'簾閻髥鹽曄獵燁葉令囹塋寧嶺嶸影怜映暎楹榮永泳渶潁濚瀛瀯煐營獰玲瑛瑩瓔盈穎纓羚聆英詠迎鈴鍈零霙靈領乂倪例刈叡曳汭濊猊睿穢芮藝蘂禮裔詣譽豫醴銳隸霓預五伍俉傲午吾吳嗚塢墺奧娛寤悟惡懊敖旿晤梧汚澳'),array(0xE8A1,0xE8FE,'烏熬獒筽蜈誤鰲鼇屋沃獄玉鈺溫瑥瘟穩縕蘊兀壅擁瓮甕癰翁邕雍饔渦瓦窩窪臥蛙蝸訛婉完宛梡椀浣玩琓琬碗緩翫脘腕莞豌阮頑曰往旺枉汪王倭娃歪矮外嵬巍猥畏了僚僥凹堯夭妖姚寥寮尿嶢拗搖撓擾料曜樂橈燎燿瑤療'),array(0xE9A1,0xE9FE,'窈窯繇繞耀腰蓼蟯要謠遙遼邀饒慾欲浴縟褥辱俑傭冗勇埇墉容庸慂榕涌湧溶熔瑢用甬聳茸蓉踊鎔鏞龍于佑偶優又友右宇寓尤愚憂旴牛玗瑀盂祐禑禹紆羽芋藕虞迂遇郵釪隅雨雩勖彧旭昱栯煜稶郁頊云暈橒殞澐熉耘芸蕓'),array(0xEAA1,0xEAFE,'運隕雲韻蔚鬱亐熊雄元原員圓園垣媛嫄寃怨愿援沅洹湲源爰猿瑗苑袁轅遠阮院願鴛月越鉞位偉僞危圍委威尉慰暐渭爲瑋緯胃萎葦蔿蝟衛褘謂違韋魏乳侑儒兪劉唯喩孺宥幼幽庾悠惟愈愉揄攸有杻柔柚柳楡楢油洧流游溜'),array(0xEBA1,0xEBFE,'濡猶猷琉瑜由留癒硫紐維臾萸裕誘諛諭踰蹂遊逾遺酉釉鍮類六堉戮毓肉育陸倫允奫尹崙淪潤玧胤贇輪鈗閏律慄栗率聿戎瀜絨融隆垠恩慇殷誾銀隱乙吟淫蔭陰音飮揖泣邑凝應膺鷹依倚儀宜意懿擬椅毅疑矣義艤薏蟻衣誼'),array(0xECA1,0xECFE,'議醫二以伊利吏夷姨履已弛彛怡易李梨泥爾珥理異痍痢移罹而耳肄苡荑裏裡貽貳邇里離飴餌匿溺瀷益翊翌翼謚人仁刃印吝咽因姻寅引忍湮燐璘絪茵藺蚓認隣靭靷鱗麟一佚佾壹日溢逸鎰馹任壬妊姙恁林淋稔臨荏賃入卄'),array(0xEDA1,0xEDFE,'立笠粒仍剩孕芿仔刺咨姉姿子字孜恣慈滋炙煮玆瓷疵磁紫者自茨蔗藉諮資雌作勺嚼斫昨灼炸爵綽芍酌雀鵲孱棧殘潺盞岑暫潛箴簪蠶雜丈仗匠場墻壯奬將帳庄張掌暲杖樟檣欌漿牆狀獐璋章粧腸臟臧莊葬蔣薔藏裝贓醬長'),array(0xEEA1,0xEEFE,'障再哉在宰才材栽梓渽滓災縡裁財載齋齎爭箏諍錚佇低儲咀姐底抵杵楮樗沮渚狙猪疽箸紵苧菹著藷詛貯躇這邸雎齟勣吊嫡寂摘敵滴狄炙的積笛籍績翟荻謫賊赤跡蹟迪迹適鏑佃佺傳全典前剪塡塼奠專展廛悛戰栓殿氈澱'),array(0xEFA1,0xEFFE,'煎琠田甸畑癲筌箋箭篆纏詮輾轉鈿銓錢鐫電顚顫餞切截折浙癤竊節絶占岾店漸点粘霑鮎點接摺蝶丁井亭停偵呈姃定幀庭廷征情挺政整旌晶晸柾楨檉正汀淀淨渟湞瀞炡玎珽町睛碇禎程穽精綎艇訂諪貞鄭酊釘鉦鋌錠霆靖'),array(0xF0A1,0xF0FE,'靜頂鼎制劑啼堤帝弟悌提梯濟祭第臍薺製諸蹄醍除際霽題齊俎兆凋助嘲弔彫措操早晁曺曹朝條棗槽漕潮照燥爪璪眺祖祚租稠窕粗糟組繰肇藻蚤詔調趙躁造遭釣阻雕鳥族簇足鏃存尊卒拙猝倧宗從悰慫棕淙琮種終綜縱腫'),array(0xF1A1,0xF1FE,'踪踵鍾鐘佐坐左座挫罪主住侏做姝胄呪周嗾奏宙州廚晝朱柱株注洲湊澍炷珠疇籌紂紬綢舟蛛註誅走躊輳週酎酒鑄駐竹粥俊儁准埈寯峻晙樽浚準濬焌畯竣蠢逡遵雋駿茁中仲衆重卽櫛楫汁葺增憎曾拯烝甑症繒蒸證贈之只'),array(0xF2A1,0xF2FE,'咫地址志持指摯支旨智枝枳止池沚漬知砥祉祗紙肢脂至芝芷蜘誌識贄趾遲直稙稷織職唇嗔塵振搢晉晋桭榛殄津溱珍瑨璡畛疹盡眞瞋秦縉縝臻蔯袗診賑軫辰進鎭陣陳震侄叱姪嫉帙桎瓆疾秩窒膣蛭質跌迭斟朕什執潗緝輯'),array(0xF3A1,0xF3FE,'鏶集徵懲澄且侘借叉嗟嵯差次此磋箚茶蹉車遮捉搾着窄錯鑿齪撰澯燦璨瓚竄簒纂粲纘讚贊鑽餐饌刹察擦札紮僭參塹慘慙懺斬站讒讖倉倡創唱娼廠彰愴敞昌昶暢槍滄漲猖瘡窓脹艙菖蒼債埰寀寨彩採砦綵菜蔡采釵冊柵策'),array(0xF4A1,0xF4FE,'責凄妻悽處倜刺剔尺慽戚拓擲斥滌瘠脊蹠陟隻仟千喘天川擅泉淺玔穿舛薦賤踐遷釧闡阡韆凸哲喆徹撤澈綴輟轍鐵僉尖沾添甛瞻簽籤詹諂堞妾帖捷牒疊睫諜貼輒廳晴淸聽菁請靑鯖切剃替涕滯締諦逮遞體初剿哨憔抄招梢'),array(0xF5A1,0xF5FE,'椒楚樵炒焦硝礁礎秒稍肖艸苕草蕉貂超酢醋醮促囑燭矗蜀觸寸忖村邨叢塚寵悤憁摠總聰蔥銃撮催崔最墜抽推椎楸樞湫皺秋芻萩諏趨追鄒酋醜錐錘鎚雛騶鰍丑畜祝竺筑築縮蓄蹙蹴軸逐春椿瑃出朮黜充忠沖蟲衝衷悴膵萃'),array(0xF6A1,0xF6FE,'贅取吹嘴娶就炊翠聚脆臭趣醉驟鷲側仄厠惻測層侈値嗤峙幟恥梔治淄熾痔痴癡稚穉緇緻置致蚩輜雉馳齒則勅飭親七柒漆侵寢枕沈浸琛砧針鍼蟄秤稱快他咤唾墮妥惰打拖朶楕舵陀馱駝倬卓啄坼度托拓擢晫柝濁濯琢琸託'),array(0xF7A1,0xF7FE,'鐸呑嘆坦彈憚歎灘炭綻誕奪脫探眈耽貪塔搭榻宕帑湯糖蕩兌台太怠態殆汰泰笞胎苔跆邰颱宅擇澤撑攄兎吐土討慟桶洞痛筒統通堆槌腿褪退頹偸套妬投透鬪慝特闖坡婆巴把播擺杷波派爬琶破罷芭跛頗判坂板版瓣販辦鈑'),array(0xF8A1,0xF8FE,'阪八叭捌佩唄悖敗沛浿牌狽稗覇貝彭澎烹膨愎便偏扁片篇編翩遍鞭騙貶坪平枰萍評吠嬖幣廢弊斃肺蔽閉陛佈包匍匏咆哺圃布怖抛抱捕暴泡浦疱砲胞脯苞葡蒲袍褒逋鋪飽鮑幅暴曝瀑爆輻俵剽彪慓杓標漂瓢票表豹飇飄驃'),array(0xF9A1,0xF9FE,'品稟楓諷豊風馮彼披疲皮被避陂匹弼必泌珌畢疋筆苾馝乏逼下何厦夏廈昰河瑕荷蝦賀遐霞鰕壑學虐謔鶴寒恨悍旱汗漢澣瀚罕翰閑閒限韓割轄函含咸啣喊檻涵緘艦銜陷鹹合哈盒蛤閤闔陜亢伉姮嫦巷恒抗杭桁沆港缸肛航'),array(0xFAA1,0xFAFE,'行降項亥偕咳垓奚孩害懈楷海瀣蟹解該諧邂駭骸劾核倖幸杏荇行享向嚮珦鄕響餉饗香噓墟虛許憲櫶獻軒歇險驗奕爀赫革俔峴弦懸晛泫炫玄玹現眩睍絃絢縣舷衒見賢鉉顯孑穴血頁嫌俠協夾峽挾浹狹脅脇莢鋏頰亨兄刑型'),array(0xFBA1,0xFBFE,'形泂滎瀅灐炯熒珩瑩荊螢衡逈邢鎣馨兮彗惠慧暳蕙蹊醯鞋乎互呼壕壺好岵弧戶扈昊晧毫浩淏湖滸澔濠濩灝狐琥瑚瓠皓祜糊縞胡芦葫蒿虎號蝴護豪鎬頀顥惑或酷婚昏混渾琿魂忽惚笏哄弘汞泓洪烘紅虹訌鴻化和嬅樺火畵'),array(0xFCA1,0xFCFE,'禍禾花華話譁貨靴廓擴攫確碻穫丸喚奐宦幻患換歡晥桓渙煥環紈還驩鰥活滑猾豁闊凰幌徨恍惶愰慌晃晄榥況湟滉潢煌璜皇篁簧荒蝗遑隍黃匯回廻徊恢悔懷晦會檜淮澮灰獪繪膾茴蛔誨賄劃獲宖橫鐄哮嚆孝效斅曉梟涍淆'),array(0xFDA1,0xFDFE,'爻肴酵驍侯候厚后吼喉嗅帿後朽煦珝逅勛勳塤壎焄熏燻薰訓暈薨喧暄煊萱卉喙毁彙徽揮暉煇諱輝麾休携烋畦虧恤譎鷸兇凶匈洶胸黑昕欣炘痕吃屹紇訖欠欽歆吸恰洽翕興僖凞喜噫囍姬嬉希憙憘戱晞曦熙熹熺犧禧稀羲詰')));
		if (!isset($m[$from]))
			return false;
		$t = &$m[$from];
		$l = strlen($str);
		$s = '';
		for ($i = 0; $i < $l; $i++) {
			$c = ord($str{$i});
			if ($c < 128)
				$s .= $str{$i};
			else {
				$c = ($c << 8) + ord($str{++$i});

				$b = 0;
				$e = count($t) - 1;
				while (1) {
					$m = floor(($b + $e) / 2);
					if ($c < $t[$m][0])
						$e = $m - 1;
					else if (($c >= $t[$m][0]) && ($c <= $t[$m][1])) {
						$o = ($c - $t[$m][0]) * 3;
						$c = ord($t[$m][2]{$o});
						if (($c & 0xE0) == 0xE0)
							$c = 3;
						else if (($c & 0xC0) == 0xC0)
							$c = 2;
						else
							$c = 1;
						$s .= substr($t[$m][2], $o, $c);
						break;
					}
					else
						$b = $m + 1;
					if ($b > $e) {
						$s .= '?';
						break;
					}
				}
			}
		}
		return $s;
	}
	else if ($from == 'UTF-8') {
		$m = array('EUC-KR' => array(array(0xA1,0x167,'........ס........ơ..ҡ........................................................................................................................................................................................................................................................................................'),array(0x2C7,0x2DD,'................................'),array(0x391,0x3C9,'¥åĥťƥǥȥɥʥ˥̥ͥΥϥХ..ҥӥԥե֥ץ................'),array(0x401,0x451,'............................ѬҬӬԬլ֬ج٬ڬ۬ܬݬެ߬..'),array(0x2015,0x203B,'............Ӣ..........................ǡ..............'),array(0x2074,0x20AC,'....................................................................................................'),array(0x2103,0x2199,'......................................................................................................................................................................................................................բآ֢٢'),array(0x21D2,0x22A5,'..........................................................................................Ӣ......ԡ........................................................š..................................................................................¡................................................................................................................'),array(0x2312,0x2312,''),array(0x2460,0x254B,'............................................................ͩΩϩЩѩҩөԩթ֩שة٩ک۩ܩݩީߩ....................................................ͨΨϨШѨҨӨԨը֨רب٨ڨۨܨݨިߨ............................................................ȦǦ¦ƦŦĦæɦʦ˦̦ͦΦϦЦѦҦӦԦզ֦צئ٦ڦۦܦݦަߦ䦶'),array(0x2592,0x25D1,'............................âǢȢˢʢɢ........................................ߡޢ........ݡܢĢ'),array(0x2605,0x266D,'ڡ..............Ϣ............................................................................................................................................................ۢ͢..ݢ'),array(0x3000,0x30F6,'..........롲......................................................................................ªêĪŪƪǪȪɪʪ˪̪ͪΪϪЪѪҪӪԪժ֪תت٪ڪ۪ܪݪުߪ..........................«ëīūƫǫȫɫʫ˫̫ͫΫϫЫѫҫӫԫի֫׫ث٫ګ۫ܫݫޫ߫'),array(0x3131,0x318E,'¤äĤŤƤǤȤɤʤˤ̤ͤΤϤФѤҤӤԤդ֤פؤ٤ڤۤܤݤޤߤ'),array(0x3200,0x321C,'©éĩũƩǩȩɩʩ˩̢'),array(0x3260,0x327F,'¨èĨŨƨǨȨɨʨ˨......'),array(0x3380,0x33DD,'ɧʧ˧̧......ܧݧާԧէ֧קا㧿§çħŧƧǧȧΧϧЧѧҧӧڧۢ짦᧼���......................'),array(0x4E00,0x7E9C,'........ز߲߾..............ܰ......................................................ӡ..............Ҭ..............޿..........................................................߭..................կ........................................................Ӣ............................˿............................ֵ................................................................................................................................ֶ......ʡ..............ӣ..........................................................ʢ................................................................................ٲ..................................................................................................................ܱ......................................ۧ..................................................ʣ̧................................................................................................................ۨ..................ߡ................................................߿........................................................................................................ʤ..................................................................................................................к........................................Ү׿..........ܲ........................................ٳ......................ή........٢......................................֩................................................................................................................................֪......................ܬ......ߢ......ξ............ʾ........................лշ......................˧..................................................................мױ..................................ʥ....֫........ҽ̤........................................................................ٴ........................................в....ڨ................................................ˡ................................................ϡ..........س..................ܦ..............................ʿհ........................................................................................................................................................................Ϣͯϣ......ͰУ..ʦ....................................٣..................................................................................ͱ......................................................................Ͳ..ګ..ʧ..........٤..........................Ϥ................................................................................................................................................ʨ..........................................................................................................................................................................................Ӻ........................................................................................................................................Ӥ..............................................................................................................................................................ʩ....................ϥ........................................................................................................................................................................................................................................................................................ޭ....................................................ҥ..............................................................................ַͳ..........................................ӥ....................................Ф..........................................г....۩....ӻ............................................................Ϧ............................................................................ϧ....................................................................................................................................................................̱..˨..................................................................................................................................................................................................................................................................................................Ӧ........................פ..................................................................................................................................................................ү........Х..........................................ҳҿ................................................................................۪................................................ٵ..............ʹ............˩..............................................................................................Ҧ......................ش..........................................ץ....................................................................................................................................................................ڬ........................................................................ʪ........................................................................................................................................................................................ޮ..............................................................................ݡ..........͵........................ݢ........................................ί............................ʫ..............Ϩ........ݣ............Ҽ............ج....һ..ΰ..............................................................................۫............................Ͷ..ڭ....................................ܳ..........צ....................ߣ........................................................................................˪................ˢ..............ھ..ֹ......................................................................................................................................................˫................................................................................................ڮ......չ......................................................................................................ϩ........................................................................................ֺ........................................ص..................................................................................................................................................ٶ..........۬........................ح................................................Ҵ....ܴ..............................................ݤ......................ͷ................ˬ..........................֯..........Ϫ....Ω................................................................ܧ................................................................................˭..........................ˮ..گ..ض..............ӧ........................ޯ..............ۭ................................................................ڰ................................................................................................................ҷ..................................................................ֻ......................̥..........................................................................ο..............Ҹ..........................................................................................................ڿ....................................................................................................................................................................................................................................ٷ................طα........................˯....................................޻..................................̨......................Ӽ........................................................................................................ϫ..........................................н..............................................ۮ......................................................................................ݦ..........................̼..............................................................................................ҹ..........Ϭ..........................................͸......................................................................................................................ظ..................................................................................................Һ..............................................................................................................Ц................................................................̩............ߺ........................................................................................................................................................................................................ؤ..........................ٸٹ..........................................߮............ҵ................................................ӵ....................̪............ӽ........................պ................................................................................................................ջ................͹......ۯ..........ͺ..............ϭ..........................ߤ......................ͻ....ݧ................ְ............ް................................ݨ......................Ө..۰..........۱........................ײ..............................ө..........................................۲....٥..................ܵ................................ܶ........................................................ع......................................ʬ..........................٦....ͼ......................ٺ................................Ӿ..........................................................................غ........................................ѡѢ..............ڱ........................................ߴ..........................ѣ..............................................................۳......................................................ͽ........ʭʮϮ....................ܷ....................ٻ....................................ϯ........ʯҰ..׳..................................................................................................................̫..................................................................................................................................................................................ڲ..........................ѥܸ........Ѥ................о......................͡..........ߵ..................ԡβ..............................................................................٧..........................................................................................ڳ........................п..................................................................۴........................................״......................;................ϰ..................................................Ϊ..................................Ч....................................ק..................ټ......................................................................................й..........Ѧ............................................................Ӫ......̬..............................................................................ޱ..........................Ԣ......................................................................................................................ձ......................ղ......ռ..............................................................ѧ....γ......................ʰ....ϱ................................................................................................................................................ֱ޲........ӫ......߯..............ϲ..........ٽ......Ը......پ..............................ϳ....................................................................................................Ѩ....................................޼............ϴ................................ߥ..........˰................................ڡ......̽....ѩ....Ѫ................ک........................................................Ϳ..............................................................................................................֬......................զ................................................׵................................................޳........................ݩ..................................................................................................................................ѫ..........................ԣר..........ӿ..................................................................................߶........Ԥ..........................ڴ........ڵ..............Ӭ..............................................................................................................................׶ϵ..٨........ݪ........͢....................................۵..................ԥ............................................................................ػ........................ש..........................خ................ؼ..................................................................̾..........................................................߻..........................................................................................Ӷ..ֲ̭....................................................Ԧ..........ս......޴..................................Թ............׷........޵........................................................ճ..........................δ....................................ؽ....................................϶......................................................................................ܹ..................................֭..........................է..............................................................................................................................................................................................֡..........................߰................................................................................................................................................................................................................................................ԧ................................................մ........................................ݫ..............................................Ժ....޶..ٿ............ڪ..................................̲....................................................................Ի................̳........................................................................Ϸ..............................................................................................................................................................................................................................................Լ..................ִ......ӷ......................................ϸѬ..................................޷........ּ........................ʱ............ߦ..........................ը..........Ш....................................Ϲ....׸............................................ѭ....Ѯ..ε......................................................׹........إ..........................................ѯ........֢......................................Ѱ............................................................................ζ................................................................ܺ....̴................................................ߧ..............ˣ................................д........ͣ................׺......................................ѱ..˱........Ѳ............˲................................ߨ........................................Ӹ..........ʲ....ܻ....................................................................ئ..............................................................................׻..........................................ת..................................................................................................ͤ....ۡ................................................................................................................٩........................................Ԩ..........................................................ڶ..........................................................................................................................................................Խ......................................ԩ........................................٪........................ؾ............ܭ..................................̡....Ϻ......................................................................ϻ......ӭ........................................................................................................................................................Щ......................................................................׼....................................ѳ............................................................ܡ..................................ا..............۶............ͥ................................................ب..........Ѵ............................................................................................................................................ѵ..........Ѷѷ........................................................................Ѹ........................................................................................Ԫ..................Ծ..............ܼҶ..........Ρ................................................................................................................................................................ԫʳͦ......................................................................................ϼ......................................................................................................Ъ..................Ы......ء..............................ܽ......................Ӯ..............................................................................آ..........ݬ..........ֽ............................................................................е........................................................................................ѹ..ߩ..............η............................................................................................................Կ............................................................................................................ӯ....................................................ֳݭ......վ....................................................................................ڷ........................................................أ..........................................................................................................................˳..................................................ͧ....Ь..Ѻ......ڢڣ..ҡ......................۷..................׫............................................̿..............................թ....................˴........̵..........Ͻ..................................................................................˵........ѻ................................................Ӱ............................֣........................................................................................................׬............................................................................ͨ..̶........................ͩ................................................տ'),array(0x7F36,0x8B9A,'ݮ............................................θ........................................................................................Ѽ....˶..ڸ....................־..............................................................................ֿ................................................................................ѽ....Ͼ..........ұ..................................................Ѿ....................................................................޽........ڤ................................֤..............................ѿ............................................̷۸............................................................................ˤ........................................................................................................................................................ޡ..............................ݯݰ....˷..................................................................۹..............̮..............................ݱ..د....................................................ޢ......................................................................................Ͽ................................................˸................ۺ..............................ݲ................................................................................................................................................ݳ......߷......................ۻ......................................................ʴ........................................................ʵ................................٫..............................................................................................................................................................................................................................................ݴذ..............................................ι........ж............΢..............................................ޣ..........................Ԭ....................................................................ؿ............................ժ............................................................................................Э....................................................................ߪ........ۼ....................................................٬......................................................................֥................................................................١......߸........׭........................................................................................................................................................................ڹ................˹......................................߱....................................................................................................................................Ϋ....................................ܢ........................................յ..................................ա................................................................................................................................ڥ..۽..................................................................................................................ӱ....................................................................................................................................ޤ................................................................................................................................................................................................................................................٭................................................բ..............................................................................................................................................................................................................................................................................ʶ........................߹..........Ҥ......................ٱ..........ʷ..............Ӳ..................................................................................֮..........................................................ޥ........................գ..........................................................................................................׮..............˺..............................................................................................................................̸......Ю..............................̯..............ԭ....................................κ........................................................................................................................ݵ........ͪ..........................................۾................ʸ......................................................................................................................Σ....................................ͫ..................Τ............ަ............................................................................................................ں..........ۿ..˻........................׽..........................................................................................................ާ..........̹....................................ܨ......................'),array(0x8C37,0x8D16,'..............ͬ..............................................................................................................................................................ݶ........޸λ..............ި......ʹ................޹......................ݷ..........Ԯ........................ݸ..................................'),array(0x8D64,0x8F62,'......................Яݹ............................................................................................................................ݺ............................ۢ....ʺ........................................Υ..................ԯ....................................................................................................................................................԰......................................................................................................................................................................................................................................................................................................................ʻ..........................................................֦ܾ....................................................................................'),array(0x8F9B,0x947F,'............ո......ܩ..........ܪ............................................................................................ʼ..............̦........ڻ............Ա................Բ........֧............................а..............................Φ....Գӹ..............̺..............................ס......ر..ܫ........դ..............................................................................................................................................ݻ........ά............................Դ................................................ީ............................................ӳ............................................................................................ٮլ....................߫............................................................................................ۣ........................................................ݼ..........................................................................................̢߼....................з............................................................˥........................................................ۤ........................................................................................ٯ................................................................................................................................................................................˼..........................................................................................................................֨Χ..Ե........................Ӵ..............................................................................................................................................................................................................................................ׯ............................................................................................................................................................................................................................................................................................'),array(0x9577,0x95E2,'................ڦ..............................˦........................б....................................................................................μ........ܣ'),array(0x961C,0x986F,'ݽ..............................................................ݾ............װ˽......................................................Զ..............................ͭ..........̰..................................................................................................................ڧ................................................................................................߬..............ܤ..........................................................ު....ڼ....................................................................................................................................................................................................................Է..............................................................................................................................................޺....................Ψ..................................׾..........................'),array(0x98A8,0x9957,'............߽................................................................................ޫ..................................................................................................ܿ..............ν............................................................................'),array(0x9996,0x9A6A,'..................................ة............................................................ʽ......ݿ........................................խ............................޾........................................................................................................................................................................'),array(0x9AA8,0x9C57,'................................................................................................................................ۥ..............................................................................................................ע....................................С........ۦ..........................ت................................................................................................................................................................................................................................................................................................................................................................................................................................................................˾..ܮ..........................'),array(0x9CE5,0x9D72,'......................ٰ............................................................................................................................................................................̻........................................................'),array(0x9DA9,0x9E1E,'................................................ͮ......................................................................................................................................................................ն'),array(0x9E75,0x9F9C,'....................................................................................................................ث......................................̣....................................................ڽ....................................ܯ..............................................................................................ެ..............................................................................................................................................ף........................'),array(0xAC00,0xB31C,'........................................................................................................................................................Ű........Ȱ............˰..Ͱΰϰ....ѰҰӰ..........................װ..ٰڰ............ܰݰ........................................................................................................................................................................................................................................................................................................ñ..........ű................................ɱ................................................................ͱ........................ѱ....................................................................ױ........ڱ۱............ݱ..............................................................................................................................................................................................................................................................................................................................................................................................................................................................................................Ĳ....ǲȲ........ʲ..................................Ͳ............ϲ............................................................................ղ........................................................................ٲ......................ݲ޲............................................................................................................................................................................................................................................................................................................................................................................................³............ĳ....................................................................................................ʳ..̳........ϳ........ѳ..ӳԳ..........ֳ׳........................۳..ݳ޳..........................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................ôĴ..........ƴ....ɴ..........................................................ϴ......................Դ..............ٴڴ......ݴ޴ߴ....................................................'),array(0xB354,0xB561,'..................................................................................................................................................................................................................................................................................................................................ʵ........................................................................ε........................ҵ............................................................................................................................................................................................................................................................................................................................................................................................................'),array(0xB5A0,0xB668,'..........................................................¶ö........................................................................................................................Ƕ..............................................................................................................................................................'),array(0xB69C,0xBF55,'Ѷ......................ն............................................................................................................................................................ܶ..........................................................................߶................................................................................................................................................................................................................................................................................................................................................................................................................ŷ..Ƿȷ............................................................η........................ҷ..................................................................................................................................................ݷ............................................................................................................................................................................................................................................................................................................................................................................................................................................................ĸŸ........................ɸ..˸̸͸..........ϸ........................................................................................................Ӹ......................ظ....۸........ݸ޸................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................̹........Ϲ............ҹ..Թչ........ٹڹ۹ܹ....޹߹..............................................................................................................................................................................................................................................................................................................................................................................................................................................................................ƺ........................ʺ........................................................................κ........ѺҺӺ..........պ..........ٺ..........................................................................................................ߺ......................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................»..ĻŻ....................................................................ǻ........................˻......'),array(0xBF94,0xBFE1,'..........................................................................................................ѻ..............................'),array(0xC03C,0xC38C,'..............................................................................ݻ............................................................................߻....................................................................................................................................................................................................................................................¼ü............ż........................ɼ..˼̼............................................................ҼӼ....................ؼ..............ݼ................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................ý........ƽ............Ƚɽ..............ν..н....................ӽ....ս..........׽ؽ........................ܽ....޽............................................................................................................................................................................'),array(0xC3C0,0xD345,'..........................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................¾................ƾ....Ⱦɾ..˾̾........ξϾ..ѾҾ......Ծ..־........................ھ..ܾݾ............߾............................................................................................................................................................................................................................ÿĿ......ǿȿ..............Ϳ........................ѿ..ӿԿ............ֿ............................................ܿ............................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................¡¢....£¤..¥¦....§..¨..©....ª........«¬..­®¯............°±....²......³..............´µ..¶·¸............¹......º................................»....................................................................¼½....¾......¿................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................áâã..........äå....æ......ç..............èé..êëì............í......î..ï..ð..............ñ........ò....................................................................óô....õ......ö..............÷ø..ùúû............üý....þ......ÿ........................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................ġĢ....ģ....ĤĥĦ............ħĨ..ĩ..Ī............īĬ....ĭ......Į..............įİ..ı..Ĳ............ĳĴ....ĵ......Ķ..............ķĸ..ĹĺĻ............ļĽ......................................ľ....................................................................Ŀ................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................šŢ..ţ..Ť............ť......Ŧ......ŧ..............Ũ......................ũŪ....ū......Ŭ..............ŭŮ......ů....................................................................Űű....Ų......ų..............Ŵŵ..Ŷ..ŷ............ŸŹ....ź......Żż............Žž..ſ..................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................ơ......................................................Ƣƣ....Ƥ......ƥ..............ƦƧ......ƨ............Ʃ......ƪ......ƫ..............Ƭ........ƭ............ƮƯ....ư....ƱƲ..Ƴ..........ƴƵ..ƶ................Ʒ......Ƹ......ƹ..............ƺƻ....................Ƽƽ....ƾ......ƿ....................................................................................................'),array(0xD37C,0xD79D,'................................................................................................................................................................................................................ǡ........................................Ǣ....................................................................ǣ......Ǥ..............................................ǥ......Ǧ......ǧ................Ǩ..ǩ................Ǫǫ....Ǭ....ǭǮ..ǯ..........ǰǱ..ǲ..ǳ............Ǵ........................................ǵ....................................................................Ƕ......Ƿ......Ǹ..............ǹ....Ǻ................ǻ......Ǽ......ǽ..............Ǿ....ǿ..................................................................................................................................................................................................................................................................................................................................................................................................................................................................................ȡ................Ȣ....................ȣȤ....ȥ......Ȧ........ȧ....Ȩȩ..Ȫ..ȫ......Ȭ....ȭȮ....ȯ......Ȱ....................ȱ..Ȳ............ȳȴ....ȵ............................ȶ..ȷ............ȸȹ....Ⱥ......Ȼ................ȼ..Ƚ..Ⱦ............ȿ............................................................................................................................................................................................................................................................................................................................................................................'),array(0xF900,0xFA0B,'έТиҢңҧҨҩҪҫҭҲҾեիծָܥݥ߳'),array(0xFF01,0xFF5E,'£ãģţƣǣȣɣʣˣ̣ͣΣϣУѣңӣԣգ֣ףأ٣ڣۡݣޣߣ'),array(0xFFE0,0xFFE6,'ˡ̡..ͣ')));
		if (!isset($m[$to]))
			return false;
		$t = &$m[$to];
		$l = strlen($str);
		$s = '';
		for ($i = 0; $i < $l; $i++) {
			$c = ord($str{$i});
			if (($c & 0xF0) == 0xF0) {
				$s .= '??';
				$i += 3;
				continue;
			}
			else if (($c & 0xE0) == 0xE0)
				$c = ($c & 0x0F) * 4096 + (ord($str{++$i}) & 0x3F) * 64 + (ord($str{++$i}) & 0x3F);
			else if (($c & 0xC0) == 0xC0)
				$c = ($c & 0x1F) * 64 + (ord($str{++$i}) & 0x3F);
			else {
				$s .= $str{$i};
				continue;
			}

			$b = 0;
			$e = count($t) - 1;
			while (1) {
				$m = floor(($b + $e) / 2);
				if ($c < $t[$m][0])
					$e = $m - 1;
				else if (($c >= $t[$m][0]) && ($c <= $t[$m][1])) {
					$s .= substr($t[$m][2], ($c - $t[$m][0]) << 1, 2);
					break;
				}
				else
					$b = $m + 1;
				if ($b > $e) {
					$s .= '??';
					break;
				}
			}
		}
		return $s;
	}
	return false;
}
}
?>
