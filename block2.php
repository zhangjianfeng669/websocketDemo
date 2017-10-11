<?php
set_time_limit(0);
$ws=socket_create(AF_INET,SOCK_STREAM,SOL_TCP) or die('canot create socket ');
socket_bind($ws,'192.168.2.133',888) or die('canot bind port 888 on server 192.168.2.133');
socket_listen($ws) or die(' canot listen port 888 on 192.168.2.133');

echo "start listen 192.168.2.133 on port 888 \r\n";

$ss=array($ws);
$cs=array();
while(1){
	
	$change=$ss;
	$write = NULL;
	$except=NULL;
	socket_select($change,$write,$except,NULL);
	foreach($change as $sock){
		
		if($sock==$ws){
			$client=socket_accept($sock);
			$ss[]=$client;
			$cs[uniqid()]=array(
				'sock'=>$client,
				'isok'=>false
			);
		}else{
			$key=getkey($sock);
			socket_recv($sock,$buf,1024,0);
			if(!$key)exit("canot find key $key");
			
			$opcode=ord($buf[0]) & 0xf;
			if($opcode==0x8){
				socket_close($sock);
				unset($cs[$key]);
				echo "close socket1\r\n";
				$mainkey=array_search($sock,$ss);
				if($mainkey!==false){
					unset($ss[$mainkey]);
					echo "close socket2\r\n";
				}
				
				continue;
			}
			
			if($cs[$key]['isok']){
				$msg=encode(decode($buf));
				//$buf=decode($buf);
				//$msg=encode('hellow');
				foreach($cs as $k=>$v){
					if($k!=$key){
						$r=socket_write($v['sock'],$msg,strlen($msg));
						if($r===false)exit("\r\nwrite failed\r\n");
					}
				}
			}else{
				preg_match("/Sec-WebSocket-Key: *(.*?)\r\n/i",$buf,$matchs);
				if(empty($matchs[1])){echo "canot find key\r\n";echo $buf;continue;}
				$h="HTTP/1.1 101 Switching Protocols\r\n";
				$h.="Upgrade: websocket\r\n";
				$h.="Connection: upgrade\r\n";
				$h.="Sec-WebSocket-Version: 13\r\n";
				$h.="Sec-WebSocket-Accept: ".base64_encode(sha1($matchs[1].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true))."\r\n\r\n";
				socket_write($sock,$h);
				
				$cs[$key]['isok']=true;
			}
		
		}
		
	}
	
}
function getkey($sock){
	global $cs;
	foreach($cs as $k=>$v){
		if($v['sock']==$sock)return $k;
	}
	return false;
}
function decode($buffer) {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        }
        else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        }
        else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        //
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }//解码其实我也没看太懂，但是使用起来是没有任何问题的
	function encode($s){
        $a = str_split($s, 125);
        //添加头文件信息，不然前台无法接受
        if (count($a) == 1){
            return "\x81" . chr(strlen($a[0])) . $a[0];
        }
        $ns = "";
        foreach ($a as $o){
            $ns .= "\x81" . chr(strlen($o)) . $o;
        }
        return $ns;
    }