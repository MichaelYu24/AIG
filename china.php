<?php
/* 
    RPC Ace v0.8.0 (RPsssC AnyCoin Explorer)

    (c) 2014 - 2015 Robin Leffmann <djinn at stolendata dot net>

    https://github.com/stolendata/rpc-ace/

    licensed under CC BY-NC-SA 4.0 - http://creativecommons.org/licenses/by-nc-sa/4.0/

 */
 
const ACE_VERSION = '1.8.8';

const RPC_HOST = '127.0.0.1';
const RPC_PORT = 11168;
const RPC_USER = 'user';
const RPC_PASS = 'password';
const COIN_NAME = '智能权证区块信息查询';
const COIN_POS = false;

const RETURN_JSON = false;
const DATE_FORMAT = 'Y-M-d H:i:s';
const BLOCKS_PER_LIST = 10;

const DB_FILE = 'false';

// for the example explorer
const COIN_HOME = 'index.php';
const REFRESH_TIME = 180;

// courtesy of https://github.com/aceat64/EasyBitcoin-PHP/
require_once( 'easybitcoin.php' );

class RPCAce
{
    private static $block_fields = [ 'hash', 'nextblockhash', 'previousblockhash', 'confirmations', 'size', 'height', 'version', 'merkleroot', 'time', 'nonce', 'bits', 'difficulty', 'mint', 'proofhash' ];

    public static function base()
    {
        $rpc = new Bitcoin( RPC_USER, RPC_PASS, RPC_HOST, RPC_PORT );
        $info = $rpc->getinfo();
        if( $rpc->status !== 200 && $rpc->error !== '' )
            return [ 'err'=>'failed to connect - node not reachable, or user/pass incorrect' ];

        if( DB_FILE )
        {
            $pdo = new PDO( 'sqlite:' . DB_FILE );
            $pdo->exec( 'create table if not exists block ( height int, hash char(64), json blob );
                         create table if not exists tx ( txid char(64), json blob );
                         create unique index if not exists ub on block ( height );
                         create unique index if not exists uh on block ( hash );
                         create unique index if not exists ut on tx ( txid );' );
        }

        $output['rpcace_version'] = ACE_VERSION;
        $output['coin_name'] = COIN_NAME;
        $output['num_blocks'] = $info['blocks'];
        $output['num_connections'] = $info['connections'];
		$output['totalcoin'] = $info['moneysupply'];

        if( COIN_POS === true )
        {
            $output['current_difficulty_pow'] = $info['difficulty']['proof-of-work'];
            $output['current_difficulty_pos'] = $info['difficulty']['proof-of-stake'];
        }
        else
            $output['current_difficulty_pow'] = $info['difficulty'];

        if( !($hashRate = @$rpc->getmininginfo()['netmhashps']) && !($hashRate = @$rpc->getmininginfo()['networkhashps'] / 1000000) )
            $hashRate = $rpc->getnetworkhashps() / 1000000;
        $output['hashrate_mhps'] = $hashRate;

        return [ 'output'=>$output, 'rpc'=>$rpc, 'pdo'=>@$pdo ];
    }

    private static function block( $base, $b )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from block where height = ? or hash = ?;' );
            $sth->execute( [$b, $b] );
            $block = $sth->fetchColumn();
            if( $block )
                $block = json_decode( gzinflate($block), true );
        }
        if( @$block == false )
        {
            if( strlen($b) < 64 )
                $b = $base['rpc']->getblockhash( $b );
            $block = $base['rpc']->getblock( $b );
        }

        if( DB_FILE && @$block )
        {
            $sth = $base['pdo']->prepare( 'insert into block values (?, ?, ?);' );
            $sth->execute( [$block['height'], $block['hash'], gzdeflate(json_encode($block))] );
        }

        return $block ? $block : false;
    }

    public static function tx( $base, $txid )
    {
        if( DB_FILE )
        {
            $sth = $base['pdo']->prepare( 'select json from tx where txid = ?;' );
            $sth->execute( [$txid] );
            $tx = $sth->fetchColumn();
            if( $tx )
                $tx = json_decode( gzinflate($tx), true );
        }
        if( @$tx == false )
            $tx = $base['rpc']->getrawtransaction( $txid, 1 );

        if( DB_FILE && @$tx )
        {
            $sth = $base['pdo']->prepare( 'insert into tx values (?, ?);' );
            $sth->execute( [$txid, gzdeflate(json_encode($tx))] );
        }
		$tx['totalcoin'] = $base['output']['totalcoin'];
        return $tx ? $tx : false;
    }

    // enumerate block details from hash
    public static function get_block( $hash )
    {
        if( preg_match('/^[0-9a-f]{64}$/i', $hash) !== 1 )
            return RETURN_JSON ? json_encode( ['err'=>'not a valid block hash'] ) : [ 'err'=>'not a valid block hash' ];

        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;

        if( ($block = self::block($base, $hash)) === false )
            return RETURN_JSON ? json_encode( ['err'=>'no block with that hash'] ) : [ 'err'=>'no block with that hash' ];

        $total = 0;
        foreach( $block as $id => $val )
            if( $id === 'tx' )
                foreach( $val as $txid )
                {
                    $transaction['id'] = $txid;
                   if( ($tx = self::tx($base, $txid)) === false )
                        continue;

                    if( isset($tx['vin'][0]['coinbase']) )
                        $transaction['coinbase'] = true;

                    foreach( $tx['vout'] as $entry )
                        if( @$entry[1]['value'] > 0.0 )
                        {
                            // nasty number formatting trick that hurts my soul, but it has to be done...
                            $total += ( $transaction['outputs'][$entry['n']]['value'] = rtrim(rtrim(sprintf('%.8f', $entry['value']), '0'), '.') );
                            $transaction['outputs'][$entry['n']]['address'] = $entry['scriptPubKey']['addresses'][0];
                        }
                    $base['output']['transactions'][] = $transaction;
                    $transaction = null;
                }
            elseif( in_array($id, self::$block_fields) )
                $base['output']['fields'][$id] = $val;

        $base['output']['total_out'] = $total;
        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }

    // create summarized list from block number
    public static function get_blocklist( $ofs, $n = BLOCKS_PER_LIST )
    {
        $base = self::base();
        if( isset($base['err']) )
            return RETURN_JSON ? json_encode( $base ) : $base;

        $offset = $ofs === null ? $base['output']['num_blocks'] : abs( (int)$ofs );
        if( $offset > $base['output']['num_blocks'] )
            return RETURN_JSON ? json_encode( ['err'=>'block does not exist'] ) : [ 'err'=>'block does not exist' ];

        $i = $offset;
        while( $i >= 0 && $n-- )
        {
            $block = self::block( $base, $i );
            $frame['hash'] = $block['hash'];
            $frame['height'] = $block['height'];
            $frame['difficulty'] = $block['difficulty'];
            $frame['time'] = $block['time'];
			$frame['date'] = date("Y-m-d H:i:s",str_replace(" UTC","",$block['time']));
            $txCount = 0;
            foreach( $block['tx'] as $txid )
            {
			   $tradeid[] = $txid;
                $txCount++;
                if( ($tx = self::tx($base, $txid)) === false )
                    continue;
				
               foreach( $tx['vout'] as $vout ){
                    $valueOut[] = $vout['value'];
			   }
            }
            $frame['tx_count'] = $txCount;
            @$frame['total_out'] = array_sum($valueOut);
			unset($valueOut);
			$frame['txid'] = $tradeid;
			unset($tradeid);
            $base['output']['blocks'][] = $frame;
            $frame = null;
            $i--;
        }

        $base['rpc'] = null;
        return RETURN_JSON ? json_encode( $base['output'] ) : $base['output'];
    }
}
?>
<?php
/*
   This is the example block explorer of RPC Ace. If you intend to use just
   the RPCAce class itself to fetch and process the array or JSON output on
   your own, you should remove this entire PHP section.
*/

$query = substr( @$_SERVER['QUERY_STRING'], 0, 64 );
if(@$_GET['tx']!=''){
	$ace = RPCAce::tx(RPCAce::base(),$_GET['tx']);
	$ace = array_merge($ace,json_decode(RPCAce::base()['rpc']->raw_response,true)['result']);
	$ace['num_blocks'] = $ace['blocks'];
	$ace['current_difficulty_pow'] = $ace['difficulty'];
	@$ace['hashrate_mhps'] = @$ace['networkhashps']/1000000;
}else{
	if( strlen($query) == 64 )
		$ace = RPCAce::get_block( $query );
	else
	{
		$query = ( $query === false || !is_numeric($query) ) ? null : abs( (int)$query );
		$ace = RPCAce::get_blocklist( $query, BLOCKS_PER_LIST );
		$query = $query === null ? @$ace['num_blocks'] : $query;
	}
}
if( isset($ace['err']) || RETURN_JSON === true )
    die( 'RPC Ace error: ' . (RETURN_JSON ? $ace : $ace['err']) );
?>
<html>
<head>
<meta charset="UTF-8">
<link rel="stylesheet" type="text/css" href="/public/abe.css" />
<link rel="shortcut icon" href="/public/favicon.ico" />
<?php
if( empty($query) || ctype_digit($query) )
    echo '<meta http-equiv="refresh" content="' . REFRESH_TIME . '; url=' . basename( __FILE__ ) . "\" />\n";
echo '<title>' . COIN_NAME . ' block explorer &middot; 智能权证 Ace v' . ACE_VERSION . "</title>\n";

$diffNom = '';
	$diff = @$ace['current_difficulty_pow'] ;
	if( COIN_POS )
	{
		$diffNom .= ' &middot; PoS';
		$diff .= ' &middot;' .   @$ace['current_difficulty_pos'] ;
	}
?>
<style>
    body{
        background: url('bg.jpg');
    }
</style>


        <link rel="stylesheet" href="./beijing_files/index.css">

  
	
	<canvas width="1366" height="0" id="canvas" style="left: 0px; position: absolute; right: 0px; top: 140px;"></canvas>
	<script type="text/javascript" src="./beijing_files/jquery-1.8.2.min.js"></script>
	<script type="text/javascript" src="./beijing_files/slider.js"></script>
	<script type="text/javascript" src="./beijing_files/canva.index.js"></script><canvas id="c_n3" width="1366" height="728" style="position: absolute; top: 0px; pointer-events: none; width: 100%; z-index: 999; opacity: 0.9;"></canvas>
<script type="text/javascript">
	;(function(){
		Clock.init('canvas');
		var avHei = window.screen.availHeight;
		$('.banner').css('height',avHei);

		$('#tream').flexslider({
            animation: "slides",
            direction: "horizontal",
            slideshow: false
        });
		//判断是否为电脑端
		function IsPC() {
		    var userAgentInfo = navigator.userAgent;
		    var Agents = ["Android", "iPhone",
		                "SymbianOS", "Windows Phone",
		                "iPad", "iPod"];
		    var flag = true;
		    for (var v = 0; v < Agents.length; v++) {
		        if (userAgentInfo.indexOf(Agents[v]) > 0) {
		            flag = false;
		            break;
		        }
		    }
		    return flag;
		}
		var flag = IsPC(); //true为PC端，false为手机端
		if(!flag){
			$('.main-title,.title-des').addClass('to-bottom');
			return false;
		}else{
			//把所有的模块距头部的高度算出来
			var introduceHei = $('.introduce').offset().top+40;
			var advantageHei = $('.advantage').offset().top+40;
			var targetHei= $('.target').offset().top+40;
			var downloadHei = $('.download').offset().top+40;
			var treamHei = $('.tream').offset().top+40;
			var accessHei = $('.access').offset().top+40;
			var cooperHei = $('.cooper').offset().top+40; 
	 		
	        //当滑动时
			$(window).scroll(function(event){
				if($(document).scrollTop()<=introduceHei ){
					$('.introduce .main-title,.introduce .title-des').addClass('to-bottom');
					//$('.introduce-con').addClass('to-top');
				}else if($(document).scrollTop()>=introduceHei & $(document).scrollTop()<=advantageHei ){
					$('.advantage .main-title,.advantage .title-des').addClass('to-bottom');
					//$('.gear-con,.innovate').addClass('to-top');
				}else if($(document).scrollTop()>=advantageHei & $(document).scrollTop()<=targetHei ){
					$('.target .main-title,.target .title-des').addClass('to-bottom');
					//$('.target-con').addClass('to-top');
				}else if($(document).scrollTop()>=targetHei & $(document).scrollTop()<=downloadHei ){
					$('.download .main-title,.download .title-des').addClass('to-bottom');
					//$('.download .wrapper').addClass('to-top');
				}else if($(document).scrollTop()>=downloadHei & $(document).scrollTop()<=treamHei ){
					$('.tream .main-title,.tream .title-des').addClass('to-bottom');
					//$('#tream').addClass('to-top');
				}
				else if($(document).scrollTop()>=treamHei & $(document).scrollTop()<=accessHei ){
					$('.access .main-title,.access .title-des').addClass('to-bottom');
					$('.cooper .main-title,.cooper .title-des').addClass('to-bottom');
					//$('.access-con').addClass('to-top');
				}else if($(document).scrollTop()>=accessHei & $(document).scrollTop()<=cooperHei ){
					$('.cooper .main-title,.cooper .title-des').addClass('to-bottom');
					//$('.cooper-con').addClass('to-top');
				}
			});
		}
		
	})();
	</script>

	
</head>
<body>
<h1 class="search_logo"><a><img src="/public/logo.png" alt="Abe logo" /></a> 智能权证区块信息查询 </h1>
<h3 class="search_logo"><p style="position: absolute;top: 50px;right: 20px;"><a href="/index.php">首页（点击返回首页）</a>&nbsp;&nbsp;&nbsp;&nbsp;语言选择：<select onChange="window.location=this.value;"></h3>
  <option value="index.php">选择(Select)</option>
  <option value="china.php">中文版(Simplified)</option>
  <option value="index.php">英文版(English)</option>
</select></p>

<div class="list">
 <div>
    <input type='text' id='page' size="64" class="search" placeholder="输入区块位置（比如第一个区块就输入1 点击搜索）"><input type='button' onclick='jump();' class="search_btn" value='搜索'>
    <br/>
    <br/>
  </div>
  <table>
    <tr>
      <td>最新区块:<a href="?<?php echo @$ace['num_blocks'];?>"><?php echo @$ace['num_blocks'];?>(点击返回)</a></td>
      <td>全网算力: <?php echo @$ace['hashrate_mhps'];?></td>
      <td>难度系数:<?php echo $diff;?></td>
      <td>货币总量:<?php echo @$ace['totalcoin'];?></td>
    </tr>
  </table>
<?php
if( isset($ace['blocks'])&& !@$_GET['tx'] )
{	
?>


 
  <table>
    <tr>
      <td><b>区块高度</b></td>
      <td><b>哈希值Hash</b></td>
      <td><b>交易数据</b></td>
      <td><b>难度</b></td>
      <td><b>生成时间</b></td>
      <td><b>区块数据</b></td>
      <td><b>输出值</b></td>
    </tr>
    <?php
    foreach( $ace['blocks'] as $block ){
		$txid = '<option value="">选择查看数量</option>';
		foreach($block['txid'] as $tx){
			$txid .= "<option value='".$tx."'>".substr($tx,0,16)."</option>";
		}
	?>
    <tr>
      <td><?php echo $block['height'];?></td>
      <td><a href="?<?php echo $block['hash'];?>"><?php echo substr( $block['hash'], 0, 16 );?>&hellip;</a></td>
      <td><select name="txid" onChange="selectjump(this)"><?php echo $txid;?></select></td>
      <td><?php echo sprintf( '%.8f', $block['difficulty']);?></td>
      <td><a title="<?php echo $block['date'];?>"><?php echo $block['date'];?></a></td>
      <td><?php echo $block['tx_count'];?></td>
      <td><?php echo sprintf( '%.2f', $block['total_out'] );?></td>
    </tr>
    <?php
	}
	$newer = $query < $ace['num_blocks'] ? '<a href="?' . ( $ace['num_blocks'] - $query >= BLOCKS_PER_LIST ? $query + BLOCKS_PER_LIST : $ace['num_blocks'] ) . '">&lt; 上一页</a>' : '&lt; 上一页';
    $older = $query - count( $ace['blocks'] ) >= 0 ? '<a href="?' . ( $query - BLOCKS_PER_LIST ) . '">下一页 &gt;</a>' : '下一页 &gt;';
	$older1 = $query - count( $ace['blocks'] ) >= 0 ? '<a href="?10' . '">第一页</a>' : '第一页';
	?>    
    <tr>
      <td colspan="7" class="urgh"> </td>
    </tr>
    <tr>
      <td colspan="7"><?php echo $newer,'&nbsp;&nbsp;&nbsp;&nbsp;',$older,'&nbsp;&nbsp;&nbsp;&nbsp;',$older1;?></td>
    </tr>
	    <tr>
		      <td colspan="7" class="urgh">智能权证版权所有 </td>
    </tr>
  </table>  
<?php
}else if(@$_GET['tx'])
{
?>
<table>

    <tr>
      <td align="left">
<p style="    text-align: left;    padding: 20px;">
交易数据:<?php echo $ace['txid'];?><br />
区块数据:<?php echo $ace['blockhash'];?><br />
交易时间:<?php echo date("Y-m-d H:i:s",$ace['time']);?><br />
生成时间:<?php echo date("Y-m-d H:i:s",$ace['blocktime']);?><br />
确认数:<?php echo $ace['confirmations'];?><br />
交易公钥签名:<?php
				foreach($ace['vout'] as $tx){
					if($tx['scriptPubKey']['asm']!=''){
						echo "<input type='text' value='".$tx['scriptPubKey']['asm']."'>&nbsp;&nbsp;";
					}	
				}	
			?><br />
交易签名:	<?php
				foreach($ace['vin'] as $tx){
					if(@$tx['scriptSig']['asm']!=''){
					echo "<input type='text' value='".$tx['scriptSig']['asm']."'>&nbsp;&nbsp;";
					}
				}	
			?><br />
交易信息(以最后两行数据为准):
			<?php
				foreach($ace['vout'] as $tx){
					echo "<br />接收地址：".@$tx['scriptPubKey']['addresses'][0]." &nbsp;&nbsp;接收数量：".@$tx['value']."&nbsp;&nbsp;";
				}	
			?>
</p>
</td></tr></table>
<?php
}
else //if( isset($ace['transactions']) )
{
	if(isset ($ace['fields']) ){
		echo '<table>    <tr>      <td align="left"><p style="    text-align: left;    padding: 20px;">';
		foreach( $ace['fields'] as $field => $val )
			if( $field == 'previousblockhash' || $field == 'nextblockhash' )
				echo "$field:<a href=\"?$val\">$val</a><br>";
			else
				echo "$field:$val<br>";
	}
	elseif(isset($ace['transactions']))
	{
		foreach( $ace['transactions'] as $tx )
		{
			echo "tx:{$tx['id']}<br>";
			foreach( $tx['outputs'] as $output )
				echo $output['value'] . ( isset( $tx['coinbase'] ) ? '*' : '' ) . " -&gt; {$output['address']}<br>";
		}
	}
    echo'</p></td></tr></table>';
}

?>

</div>
</body>
</html>
<script type="text/javascript">
	function jump(){
		var id = document.getElementById('page').value;
		window.location.href="?"+id;
	}
	function selectjump(e){
		window.location.href="?tx="+e.value;
	}
</script>
