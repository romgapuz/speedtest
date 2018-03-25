<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no" />
<title>AWS Speedtest</title>
<style type="text/css">
	html,body{
		border:none; padding:0; margin:0;
		background:#FFFFFF;
		color:#202020;
	}
	body{
		text-align:center;
		font-family:"Roboto",sans-serif;
	}
	h1{
		color:#404040;
	}
	#startStopBtn{
		display:inline-block;
		margin:0 auto;
		color:#6060AA;
		background-color:rgba(0,0,0,0);
		border:0.15em solid #6060FF;
		border-radius:0.3em;
		transition:all 0.3s;
		box-sizing:border-box;
		width:8em; height:3em;
		line-height:2.7em;
		cursor:pointer;
		box-shadow: 0 0 0 rgba(0,0,0,0.1), inset 0 0 0 rgba(0,0,0,0.1);
	}
	#startStopBtn:hover{
		box-shadow: 0 0 2em rgba(0,0,0,0.1), inset 0 0 1em rgba(0,0,0,0.1);
	}
	#startStopBtn.running{
		background-color:#FF3030;
		border-color:#FF6060;
		color:#FFFFFF;
	}
	#startStopBtn:before{
		content:"Start";
	}
	#startStopBtn.running:before{
		content:"Abort";
	}
	#test{
		margin-top:2em;
		margin-bottom:12em;
	}
	div.testArea{
		display:inline-block;
		width:16em;
		height:12.5em;
		position:relative;
		box-sizing:border-box;
	}
	div.testName{
		position:absolute;
		top:0.1em; left:0;
		width:100%;
		font-size:1.4em;
		z-index:9;
	}
	div.meterText{
		position:absolute;
		bottom:1.55em; left:0;
		width:100%;
		font-size:2.5em;
		z-index:9;
	}
	div.meterText:empty:before{
		content:"0.00";
	}
	div.unit{
		position:absolute;
		bottom:2em; left:0;
		width:100%;
		z-index:9;
	}
	div.testArea canvas{
		position:absolute;
		top:0; left:0; width:100%; height:100%;
		z-index:1;
	}
	div.testGroup{
		display:inline-block;
	}
	@media all and (max-width:65em){
		body{
			font-size:1.5vw;
		}
	}
	@media all and (max-width:40em){
		body{
			font-size:0.8em;
		}
		div.testGroup{
			display:block;
			margin: 0 auto;
		}
	}
	.divTable{
		display: table;
		width: 769px;
		margin: 0 auto;
	}
	.divTableRow {
		display: table-row;
	}
	.divTableHeading {
		background-color: #EEE;
		display: table-header-group;
	}
	.divTableCell, .divTableHead {
		border: 1px solid #999999;
		display: table-cell;
		padding: 3px 10px;
	}
	.divTableHeading {
		background-color: #EEE;
		display: table-header-group;
		font-weight: bold;
	}
	.divTableFoot {
		background-color: #EEE;
		display: table-footer-group;
		font-weight: bold;
	}
	.divTableBody {
		display: table-row-group;
	}
	#calculateBandwidthBtn{
		display:inline-block;
		margin:0 auto;
		color:#6060AA;
		background-color:rgba(0,0,0,0);
		border:0.15em solid #6060FF;
		border-radius:0.3em;
		transition:all 0.3s;
		box-sizing:border-box;
		width:8em; height:3em;
		line-height:2.7em;
		cursor:pointer;
		box-shadow: 0 0 0 rgba(0,0,0,0.1), inset 0 0 0 rgba(0,0,0,0.1);
	}
	#calculateBandwidthBtn:hover{
		box-shadow: 0 0 2em rgba(0,0,0,0.1), inset 0 0 1em rgba(0,0,0,0.1);
	}
</style>
<script type="text/javascript">
function I(id){return document.getElementById(id);}
var meterBk="#E0E0E0";
var dlColor="#6060AA",
	ulColor="#309030",
	pingColor="#AA6060",
	jitColor="#AA6060";
var progColor="#EEEEEE";

//CODE FOR GAUGES
function drawMeter(c,amount,bk,fg,progress,prog){
	var ctx=c.getContext("2d");
	var dp=window.devicePixelRatio||1;
	var cw=c.clientWidth*dp, ch=c.clientHeight*dp;
	var sizScale=ch*0.0055;
	if(c.width==cw&&c.height==ch){
		ctx.clearRect(0,0,cw,ch);
	}else{
		c.width=cw;
		c.height=ch;
	}
	ctx.beginPath();
	ctx.strokeStyle=bk;
	ctx.lineWidth=16*sizScale;
	ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,Math.PI*0.1);
	ctx.stroke();
	ctx.beginPath();
	ctx.strokeStyle=fg;
	ctx.lineWidth=16*sizScale;
	ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,amount*Math.PI*1.2-Math.PI*1.1);
	ctx.stroke();
	if(typeof progress !== "undefined"){
		ctx.fillStyle=prog;
		ctx.fillRect(c.width*0.3,c.height-16*sizScale,c.width*0.4*progress,4*sizScale);
	}
}
function mbpsToAmount(s){
	return 1-(1/(Math.pow(1.3,Math.sqrt(s))));
}
function msToAmount(s){
	return 1-(1/(Math.pow(1.08,Math.sqrt(s))));
}

//SPEEDTEST AND UI CODE
var w=null; //speedtest worker
var data=null; //data from worker
function startStop(){
	if(w!=null){
		//speedtest is running, abort
		w.postMessage('abort');
		w=null;
		data=null;
		I("startStopBtn").className="";
		initUI();
	}else{
		//test is not running, begin
		w=new Worker('speedtest_worker.min.js');
		w.postMessage('start'); //Add optional parameters as a JSON object to this command
		I("startStopBtn").className="running";
		w.onmessage=function(e){
			data=e.data.split(';');
			var status=Number(data[0]);
			if(status>=4){
				//test completed
				I("startStopBtn").className="";
				w=null;
				updateUI(true);
			}
		};
	}
}
//this function reads the data sent back by the worker and updates the UI
function updateUI(forced){
	if(!forced&&(!data||!w)) return;
	var status=Number(data[0]);
	I("ip").textContent=data[4];
	I("dlText").textContent=(status==1&&data[1]==0)?"...":data[1];
	drawMeter(I("dlMeter"),mbpsToAmount(Number(data[1]*(status==1?oscillate():1))),meterBk,dlColor,Number(data[6]),progColor);
	I("ulText").textContent=(status==3&&data[2]==0)?"...":data[2];
	drawMeter(I("ulMeter"),mbpsToAmount(Number(data[2]*(status==3?oscillate():1))),meterBk,ulColor,Number(data[7]),progColor);
	I("pingText").textContent=data[3];
	drawMeter(I("pingMeter"),msToAmount(Number(data[3]*(status==2?oscillate():1))),meterBk,pingColor,Number(data[8]),progColor);
	I("jitText").textContent=data[5];
	drawMeter(I("jitMeter"),msToAmount(Number(data[5]*(status==2?oscillate():1))),meterBk,jitColor,Number(data[8]),progColor);
}
function oscillate(){
	return 1+0.02*Math.sin(Date.now()/100);
}
//poll the status from the worker (this will call updateUI)
setInterval(function(){
	if(w) w.postMessage('status');
},200);
//update the UI every frame
window.requestAnimationFrame=window.requestAnimationFrame||window.webkitRequestAnimationFrame||window.mozRequestAnimationFrame||window.msRequestAnimationFrame||(function(callback,element){setTimeout(callback,1000/60);});
function frame(){
	requestAnimationFrame(frame);
	updateUI();
}
frame(); //start frame loop
//function to (re)initialize UI
function initUI(){
	drawMeter(I("dlMeter"),0,meterBk,dlColor,0);
	drawMeter(I("ulMeter"),0,meterBk,ulColor,0);
	drawMeter(I("pingMeter"),0,meterBk,pingColor,0);
	drawMeter(I("jitMeter"),0,meterBk,jitColor,0);
	I("dlText").textContent="";
	I("ulText").textContent="";
	I("pingText").textContent="";
	I("jitText").textContent="";
	I("ip").textContent="";
	I("dl25").textContent="";
	I("dl50").textContent="";
	I("dl75").textContent="";
	I("dl100").textContent="";
	I("ul25").textContent="";
	I("ul50").textContent="";
	I("ul75").textContent="";
	I("ul100").textContent="";
}
function calculateBandwidth(){
	multiplier = 0;
	if (document.getElementById("bunit").value=="GB")
		multiplier = 1000;
	else if (document.getElementById("bunit").value=="TB")
		multiplier = 1000000;
	else
		multiplier = 1000000000;
	binmb = document.getElementById("bamt").value*multiplier;
	dldays = (binmb/I("dlText").textContent)/86400;
	dldays25 = (binmb/(I("dlText").textContent*0.25))/86400;
	dldays50 = (binmb/(I("dlText").textContent*0.5))/86400;
	dldays75 = (binmb/(I("dlText").textContent*0.75))/86400;
	I("dl100").textContent=moment.duration(dldays, "days").humanize();
	I("dl25").textContent=moment.duration(dldays25, "days").humanize();
	I("dl50").textContent=moment.duration(dldays50, "days").humanize();
	I("dl75").textContent=moment.duration(dldays75, "days").humanize();
	uldays = (binmb/I("ulText").textContent)/86400;
	uldays25 = (binmb/(I("ulText").textContent*0.25))/86400;
	uldays50 = (binmb/(I("ulText").textContent*0.5))/86400;
	uldays75 = (binmb/(I("ulText").textContent*0.75))/86400;
	I("ul100").textContent=moment.duration(uldays, "days").humanize();
	I("ul25").textContent=moment.duration(uldays25, "days").humanize();
	I("ul50").textContent=moment.duration(uldays50, "days").humanize();
	I("ul75").textContent=moment.duration(uldays75, "days").humanize();
}

</script>
<script type="text/javascript" src="moment.min.js"></script>
</head>
<body>
<h1>AWS Speedtest (<?php $inst_ident=file_get_contents("http://instance-data/latest/dynamic/instance-identity/document"); $inst_ident_json = json_decode($inst_ident); echo $inst_ident_json->region; ?>)</h1>
<div id="startStopBtn" onclick="startStop()"></div>
<div id="test">
	<div class="testGroup">
		<div class="testArea">
			<div class="testName">Download</div>
			<canvas id="dlMeter" class="meter"></canvas>
			<div id="dlText" class="meterText"></div>
			<div class="unit">Mbps</div>
		</div>
		<div class="testArea">
			<div class="testName">Upload</div>
			<canvas id="ulMeter" class="meter"></canvas>
			<div id="ulText" class="meterText"></div>
			<div class="unit">Mbps</div>
		</div>
	</div>
	<div class="testGroup">
		<div class="testArea">
			<div class="testName">Ping</div>
			<canvas id="pingMeter" class="meter"></canvas>
			<div id="pingText" class="meterText"></div>
			<div class="unit">ms</div>
		</div>
		<div class="testArea">
			<div class="testName">Jitter</div>
			<canvas id="jitMeter" class="meter"></canvas>
			<div id="jitText" class="meterText"></div>
			<div class="unit">ms</div>
		</div>
	</div>
	<div id="ipArea">
		IP Address: <span id="ip"></span>
	</div>
	<br />
	<h3>Bandwith Calculator</h3>
	<div>
		Based on your current bandwidth, to download/upload <input id="bamt" type="number" value="100" /> <select id="bunit">
			<option value="GB" selected>GB</option>
			<option value="TB">TB</option>
			<option value="PB">PB</option>
		</select> the estimated time to finish is below:
	</div>
	<br />
	<div class="divTable">
		<div class="divTableBody">
			<div class="divTableRow">
				<div class="divTableCell"><b>Utilization</b></div>
				<div class="divTableCell"><b>Download</b></div>
				<div class="divTableCell"><b>Upload</b></div>
			</div>
			<div class="divTableRow">
				<div class="divTableCell"><b>25%</b></div>
				<div class="divTableCell"><div id="dl25"></div></div>
				<div class="divTableCell"><div id="ul25"></div></div>
			</div>
			<div class="divTableRow">
				<div class="divTableCell"><b>50%</b></div>
				<div class="divTableCell"><div id="dl50"></div></div>
				<div class="divTableCell"><div id="ul50"></div></div>
			</div>
			<div class="divTableRow">
				<div class="divTableCell"><b>75%</b></div>
				<div class="divTableCell"><div id="dl75"></div></div>
				<div class="divTableCell"><div id="ul75"></div></div>
			</div>
			<div class="divTableRow">
				<div class="divTableCell"><b>100%</b></div>
				<div class="divTableCell"><div id="dl100"></div></div>
				<div class="divTableCell"><div id="ul100"></div></div>
			</div>
		</div>
	</div>
	<br />
	<div id="calculateBandwidthBtn" onclick="calculateBandwidth()">Calculate</div>
</div>
Based on the open source project at <a href="https://github.com/adolfintel/speedtest" target="_blank">https://github.com/adolfintel/speedtest</a>
<script type="text/javascript">setTimeout(initUI,100);</script>
</body>
</html>