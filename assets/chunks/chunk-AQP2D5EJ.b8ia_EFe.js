import{g as te}from"./chunk-55IACEB6.D75z0lUt.js";import{s as ee}from"./chunk-2J33WTMH.CgbvVfK-.js";import{_ as u,l as b,c as w,r as se,u as ie,a as re,b as ae,g as ne,s as oe,q as le,t as ce,ab as he,k as W,A as ue}from"./mermaid.core.nRjnRDqC.js";var vt=(function(){var t=u(function(Y,o,c,a){for(c=c||{},a=Y.length;a--;c[Y[a]]=o);return c},"o"),e=[1,2],s=[1,3],n=[1,4],r=[2,4],d=[1,9],f=[1,11],S=[1,16],p=[1,17],T=[1,18],E=[1,19],m=[1,33],x=[1,20],D=[1,21],h=[1,22],L=[1,23],v=[1,24],$=[1,26],I=[1,27],P=[1,28],O=[1,29],q=[1,30],it=[1,31],rt=[1,32],at=[1,35],nt=[1,36],ot=[1,37],lt=[1,38],H=[1,34],y=[1,4,5,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],ct=[1,4,5,14,15,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,39,40,41,45,48,51,52,53,54,57],xt=[4,5,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],gt={trace:u(function(){},"trace"),yy:{},symbols_:{error:2,start:3,SPACE:4,NL:5,SD:6,document:7,line:8,statement:9,classDefStatement:10,styleStatement:11,cssClassStatement:12,idStatement:13,DESCR:14,"-->":15,HIDE_EMPTY:16,scale:17,WIDTH:18,COMPOSIT_STATE:19,STRUCT_START:20,STRUCT_STOP:21,STATE_DESCR:22,AS:23,ID:24,FORK:25,JOIN:26,CHOICE:27,CONCURRENT:28,note:29,notePosition:30,NOTE_TEXT:31,direction:32,acc_title:33,acc_title_value:34,acc_descr:35,acc_descr_value:36,acc_descr_multiline_value:37,CLICK:38,STRING:39,HREF:40,classDef:41,CLASSDEF_ID:42,CLASSDEF_STYLEOPTS:43,DEFAULT:44,style:45,STYLE_IDS:46,STYLEDEF_STYLEOPTS:47,class:48,CLASSENTITY_IDS:49,STYLECLASS:50,direction_tb:51,direction_bt:52,direction_rl:53,direction_lr:54,eol:55,";":56,EDGE_STATE:57,STYLE_SEPARATOR:58,left_of:59,right_of:60,$accept:0,$end:1},terminals_:{2:"error",4:"SPACE",5:"NL",6:"SD",14:"DESCR",15:"-->",16:"HIDE_EMPTY",17:"scale",18:"WIDTH",19:"COMPOSIT_STATE",20:"STRUCT_START",21:"STRUCT_STOP",22:"STATE_DESCR",23:"AS",24:"ID",25:"FORK",26:"JOIN",27:"CHOICE",28:"CONCURRENT",29:"note",31:"NOTE_TEXT",33:"acc_title",34:"acc_title_value",35:"acc_descr",36:"acc_descr_value",37:"acc_descr_multiline_value",38:"CLICK",39:"STRING",40:"HREF",41:"classDef",42:"CLASSDEF_ID",43:"CLASSDEF_STYLEOPTS",44:"DEFAULT",45:"style",46:"STYLE_IDS",47:"STYLEDEF_STYLEOPTS",48:"class",49:"CLASSENTITY_IDS",50:"STYLECLASS",51:"direction_tb",52:"direction_bt",53:"direction_rl",54:"direction_lr",56:";",57:"EDGE_STATE",58:"STYLE_SEPARATOR",59:"left_of",60:"right_of"},productions_:[0,[3,2],[3,2],[3,2],[7,0],[7,2],[8,2],[8,1],[8,1],[9,1],[9,1],[9,1],[9,1],[9,2],[9,3],[9,4],[9,1],[9,2],[9,1],[9,4],[9,3],[9,6],[9,1],[9,1],[9,1],[9,1],[9,4],[9,4],[9,1],[9,2],[9,2],[9,1],[9,5],[9,5],[10,3],[10,3],[11,3],[12,3],[32,1],[32,1],[32,1],[32,1],[55,1],[55,1],[13,1],[13,1],[13,3],[13,3],[30,1],[30,1]],performAction:u(function(o,c,a,g,_,i,F){var l=i.length-1;switch(_){case 3:return g.setRootDoc(i[l]),i[l];case 4:this.$=[];break;case 5:i[l]!="nl"&&(i[l-1].push(i[l]),this.$=i[l-1]);break;case 6:case 7:this.$=i[l];break;case 8:this.$="nl";break;case 12:this.$=i[l];break;case 13:const Q=i[l-1];Q.description=g.trimColon(i[l]),this.$=Q;break;case 14:this.$={stmt:"relation",state1:i[l-2],state2:i[l]};break;case 15:const Tt=g.trimColon(i[l]);this.$={stmt:"relation",state1:i[l-3],state2:i[l-1],description:Tt};break;case 19:this.$={stmt:"state",id:i[l-3],type:"default",description:"",doc:i[l-1]};break;case 20:var B=i[l],z=i[l-2].trim();if(i[l].match(":")){var ut=i[l].split(":");B=ut[0],z=[z,ut[1]]}this.$={stmt:"state",id:B,type:"default",description:z};break;case 21:this.$={stmt:"state",id:i[l-3],type:"default",description:i[l-5],doc:i[l-1]};break;case 22:this.$={stmt:"state",id:i[l],type:"fork"};break;case 23:this.$={stmt:"state",id:i[l],type:"join"};break;case 24:this.$={stmt:"state",id:i[l],type:"choice"};break;case 25:this.$={stmt:"state",id:g.getDividerId(),type:"divider"};break;case 26:this.$={stmt:"state",id:i[l-1].trim(),note:{position:i[l-2].trim(),text:i[l].trim()}};break;case 29:this.$=i[l].trim(),g.setAccTitle(this.$);break;case 30:case 31:this.$=i[l].trim(),g.setAccDescription(this.$);break;case 32:this.$={stmt:"click",id:i[l-3],url:i[l-2],tooltip:i[l-1]};break;case 33:this.$={stmt:"click",id:i[l-3],url:i[l-1],tooltip:""};break;case 34:case 35:this.$={stmt:"classDef",id:i[l-1].trim(),classes:i[l].trim()};break;case 36:this.$={stmt:"style",id:i[l-1].trim(),styleClass:i[l].trim()};break;case 37:this.$={stmt:"applyClass",id:i[l-1].trim(),styleClass:i[l].trim()};break;case 38:g.setDirection("TB"),this.$={stmt:"dir",value:"TB"};break;case 39:g.setDirection("BT"),this.$={stmt:"dir",value:"BT"};break;case 40:g.setDirection("RL"),this.$={stmt:"dir",value:"RL"};break;case 41:g.setDirection("LR"),this.$={stmt:"dir",value:"LR"};break;case 44:case 45:this.$={stmt:"state",id:i[l].trim(),type:"default",description:""};break;case 46:this.$={stmt:"state",id:i[l-2].trim(),classes:[i[l].trim()],type:"default",description:""};break;case 47:this.$={stmt:"state",id:i[l-2].trim(),classes:[i[l].trim()],type:"default",description:""};break}},"anonymous"),table:[{3:1,4:e,5:s,6:n},{1:[3]},{3:5,4:e,5:s,6:n},{3:6,4:e,5:s,6:n},t([1,4,5,16,17,19,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],r,{7:7}),{1:[2,1]},{1:[2,2]},{1:[2,3],4:d,5:f,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:p,19:T,22:E,24:m,25:x,26:D,27:h,28:L,29:v,32:25,33:$,35:I,37:P,38:O,41:q,45:it,48:rt,51:at,52:nt,53:ot,54:lt,57:H},t(y,[2,5]),{9:39,10:12,11:13,12:14,13:15,16:S,17:p,19:T,22:E,24:m,25:x,26:D,27:h,28:L,29:v,32:25,33:$,35:I,37:P,38:O,41:q,45:it,48:rt,51:at,52:nt,53:ot,54:lt,57:H},t(y,[2,7]),t(y,[2,8]),t(y,[2,9]),t(y,[2,10]),t(y,[2,11]),t(y,[2,12],{14:[1,40],15:[1,41]}),t(y,[2,16]),{18:[1,42]},t(y,[2,18],{20:[1,43]}),{23:[1,44]},t(y,[2,22]),t(y,[2,23]),t(y,[2,24]),t(y,[2,25]),{30:45,31:[1,46],59:[1,47],60:[1,48]},t(y,[2,28]),{34:[1,49]},{36:[1,50]},t(y,[2,31]),{13:51,24:m,57:H},{42:[1,52],44:[1,53]},{46:[1,54]},{49:[1,55]},t(ct,[2,44],{58:[1,56]}),t(ct,[2,45],{58:[1,57]}),t(y,[2,38]),t(y,[2,39]),t(y,[2,40]),t(y,[2,41]),t(y,[2,6]),t(y,[2,13]),{13:58,24:m,57:H},t(y,[2,17]),t(xt,r,{7:59}),{24:[1,60]},{24:[1,61]},{23:[1,62]},{24:[2,48]},{24:[2,49]},t(y,[2,29]),t(y,[2,30]),{39:[1,63],40:[1,64]},{43:[1,65]},{43:[1,66]},{47:[1,67]},{50:[1,68]},{24:[1,69]},{24:[1,70]},t(y,[2,14],{14:[1,71]}),{4:d,5:f,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:p,19:T,21:[1,72],22:E,24:m,25:x,26:D,27:h,28:L,29:v,32:25,33:$,35:I,37:P,38:O,41:q,45:it,48:rt,51:at,52:nt,53:ot,54:lt,57:H},t(y,[2,20],{20:[1,73]}),{31:[1,74]},{24:[1,75]},{39:[1,76]},{39:[1,77]},t(y,[2,34]),t(y,[2,35]),t(y,[2,36]),t(y,[2,37]),t(ct,[2,46]),t(ct,[2,47]),t(y,[2,15]),t(y,[2,19]),t(xt,r,{7:78}),t(y,[2,26]),t(y,[2,27]),{5:[1,79]},{5:[1,80]},{4:d,5:f,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:p,19:T,21:[1,81],22:E,24:m,25:x,26:D,27:h,28:L,29:v,32:25,33:$,35:I,37:P,38:O,41:q,45:it,48:rt,51:at,52:nt,53:ot,54:lt,57:H},t(y,[2,32]),t(y,[2,33]),t(y,[2,21])],defaultActions:{5:[2,1],6:[2,2],47:[2,48],48:[2,49]},parseError:u(function(o,c){if(c.recoverable)this.trace(o);else{var a=new Error(o);throw a.hash=c,a}},"parseError"),parse:u(function(o){var c=this,a=[0],g=[],_=[null],i=[],F=this.table,l="",B=0,z=0,ut=2,Q=1,Tt=i.slice.call(arguments,1),k=Object.create(this.lexer),M={yy:{}};for(var Et in this.yy)Object.prototype.hasOwnProperty.call(this.yy,Et)&&(M.yy[Et]=this.yy[Et]);k.setInput(o,M.yy),M.yy.lexer=k,M.yy.parser=this,typeof k.yylloc>"u"&&(k.yylloc={});var _t=k.yylloc;i.push(_t);var Qt=k.options&&k.options.ranges;typeof M.yy.parseError=="function"?this.parseError=M.yy.parseError:this.parseError=Object.getPrototypeOf(this).parseError;function Zt(N){a.length=a.length-2*N,_.length=_.length-N,i.length=i.length-N}u(Zt,"popStack");function Lt(){var N;return N=g.pop()||k.lex()||Q,typeof N!="number"&&(N instanceof Array&&(g=N,N=g.pop()),N=c.symbols_[N]||N),N}u(Lt,"lex");for(var A,U,R,mt,K={},dt,G,It,ft;;){if(U=a[a.length-1],this.defaultActions[U]?R=this.defaultActions[U]:((A===null||typeof A>"u")&&(A=Lt()),R=F[U]&&F[U][A]),typeof R>"u"||!R.length||!R[0]){var bt="";ft=[];for(dt in F[U])this.terminals_[dt]&&dt>ut&&ft.push("'"+this.terminals_[dt]+"'");k.showPosition?bt="Parse error on line "+(B+1)+`:
`+k.showPosition()+`
Expecting `+ft.join(", ")+", got '"+(this.terminals_[A]||A)+"'":bt="Parse error on line "+(B+1)+": Unexpected "+(A==Q?"end of input":"'"+(this.terminals_[A]||A)+"'"),this.parseError(bt,{text:k.match,token:this.terminals_[A]||A,line:k.yylineno,loc:_t,expected:ft})}if(R[0]instanceof Array&&R.length>1)throw new Error("Parse Error: multiple actions possible at state: "+U+", token: "+A);switch(R[0]){case 1:a.push(A),_.push(k.yytext),i.push(k.yylloc),a.push(R[1]),A=null,z=k.yyleng,l=k.yytext,B=k.yylineno,_t=k.yylloc;break;case 2:if(G=this.productions_[R[1]][1],K.$=_[_.length-G],K._$={first_line:i[i.length-(G||1)].first_line,last_line:i[i.length-1].last_line,first_column:i[i.length-(G||1)].first_column,last_column:i[i.length-1].last_column},Qt&&(K._$.range=[i[i.length-(G||1)].range[0],i[i.length-1].range[1]]),mt=this.performAction.apply(K,[l,z,B,M.yy,R[1],_,i].concat(Tt)),typeof mt<"u")return mt;G&&(a=a.slice(0,-1*G*2),_=_.slice(0,-1*G),i=i.slice(0,-1*G)),a.push(this.productions_[R[1]][0]),_.push(K.$),i.push(K._$),It=F[a[a.length-2]][a[a.length-1]],a.push(It);break;case 3:return!0}}return!0},"parse")},qt=(function(){var Y={EOF:1,parseError:u(function(c,a){if(this.yy.parser)this.yy.parser.parseError(c,a);else throw new Error(c)},"parseError"),setInput:u(function(o,c){return this.yy=c||this.yy||{},this._input=o,this._more=this._backtrack=this.done=!1,this.yylineno=this.yyleng=0,this.yytext=this.matched=this.match="",this.conditionStack=["INITIAL"],this.yylloc={first_line:1,first_column:0,last_line:1,last_column:0},this.options.ranges&&(this.yylloc.range=[0,0]),this.offset=0,this},"setInput"),input:u(function(){var o=this._input[0];this.yytext+=o,this.yyleng++,this.offset++,this.match+=o,this.matched+=o;var c=o.match(/(?:\r\n?|\n).*/g);return c?(this.yylineno++,this.yylloc.last_line++):this.yylloc.last_column++,this.options.ranges&&this.yylloc.range[1]++,this._input=this._input.slice(1),o},"input"),unput:u(function(o){var c=o.length,a=o.split(/(?:\r\n?|\n)/g);this._input=o+this._input,this.yytext=this.yytext.substr(0,this.yytext.length-c),this.offset-=c;var g=this.match.split(/(?:\r\n?|\n)/g);this.match=this.match.substr(0,this.match.length-1),this.matched=this.matched.substr(0,this.matched.length-1),a.length-1&&(this.yylineno-=a.length-1);var _=this.yylloc.range;return this.yylloc={first_line:this.yylloc.first_line,last_line:this.yylineno+1,first_column:this.yylloc.first_column,last_column:a?(a.length===g.length?this.yylloc.first_column:0)+g[g.length-a.length].length-a[0].length:this.yylloc.first_column-c},this.options.ranges&&(this.yylloc.range=[_[0],_[0]+this.yyleng-c]),this.yyleng=this.yytext.length,this},"unput"),more:u(function(){return this._more=!0,this},"more"),reject:u(function(){if(this.options.backtrack_lexer)this._backtrack=!0;else return this.parseError("Lexical error on line "+(this.yylineno+1)+`. You can only invoke reject() in the lexer when the lexer is of the backtracking persuasion (options.backtrack_lexer = true).
`+this.showPosition(),{text:"",token:null,line:this.yylineno});return this},"reject"),less:u(function(o){this.unput(this.match.slice(o))},"less"),pastInput:u(function(){var o=this.matched.substr(0,this.matched.length-this.match.length);return(o.length>20?"...":"")+o.substr(-20).replace(/\n/g,"")},"pastInput"),upcomingInput:u(function(){var o=this.match;return o.length<20&&(o+=this._input.substr(0,20-o.length)),(o.substr(0,20)+(o.length>20?"...":"")).replace(/\n/g,"")},"upcomingInput"),showPosition:u(function(){var o=this.pastInput(),c=new Array(o.length+1).join("-");return o+this.upcomingInput()+`
`+c+"^"},"showPosition"),test_match:u(function(o,c){var a,g,_;if(this.options.backtrack_lexer&&(_={yylineno:this.yylineno,yylloc:{first_line:this.yylloc.first_line,last_line:this.last_line,first_column:this.yylloc.first_column,last_column:this.yylloc.last_column},yytext:this.yytext,match:this.match,matches:this.matches,matched:this.matched,yyleng:this.yyleng,offset:this.offset,_more:this._more,_input:this._input,yy:this.yy,conditionStack:this.conditionStack.slice(0),done:this.done},this.options.ranges&&(_.yylloc.range=this.yylloc.range.slice(0))),g=o[0].match(/(?:\r\n?|\n).*/g),g&&(this.yylineno+=g.length),this.yylloc={first_line:this.yylloc.last_line,last_line:this.yylineno+1,first_column:this.yylloc.last_column,last_column:g?g[g.length-1].length-g[g.length-1].match(/\r?\n?/)[0].length:this.yylloc.last_column+o[0].length},this.yytext+=o[0],this.match+=o[0],this.matches=o,this.yyleng=this.yytext.length,this.options.ranges&&(this.yylloc.range=[this.offset,this.offset+=this.yyleng]),this._more=!1,this._backtrack=!1,this._input=this._input.slice(o[0].length),this.matched+=o[0],a=this.performAction.call(this,this.yy,this,c,this.conditionStack[this.conditionStack.length-1]),this.done&&this._input&&(this.done=!1),a)return a;if(this._backtrack){for(var i in _)this[i]=_[i];return!1}return!1},"test_match"),next:u(function(){if(this.done)return this.EOF;this._input||(this.done=!0);var o,c,a,g;this._more||(this.yytext="",this.match="");for(var _=this._currentRules(),i=0;i<_.length;i++)if(a=this._input.match(this.rules[_[i]]),a&&(!c||a[0].length>c[0].length)){if(c=a,g=i,this.options.backtrack_lexer){if(o=this.test_match(a,_[i]),o!==!1)return o;if(this._backtrack){c=!1;continue}else return!1}else if(!this.options.flex)break}return c?(o=this.test_match(c,_[g]),o!==!1?o:!1):this._input===""?this.EOF:this.parseError("Lexical error on line "+(this.yylineno+1)+`. Unrecognized text.
`+this.showPosition(),{text:"",token:null,line:this.yylineno})},"next"),lex:u(function(){var c=this.next();return c||this.lex()},"lex"),begin:u(function(c){this.conditionStack.push(c)},"begin"),popState:u(function(){var c=this.conditionStack.length-1;return c>0?this.conditionStack.pop():this.conditionStack[0]},"popState"),_currentRules:u(function(){return this.conditionStack.length&&this.conditionStack[this.conditionStack.length-1]?this.conditions[this.conditionStack[this.conditionStack.length-1]].rules:this.conditions.INITIAL.rules},"_currentRules"),topState:u(function(c){return c=this.conditionStack.length-1-Math.abs(c||0),c>=0?this.conditionStack[c]:"INITIAL"},"topState"),pushState:u(function(c){this.begin(c)},"pushState"),stateStackSize:u(function(){return this.conditionStack.length},"stateStackSize"),options:{"case-insensitive":!0},performAction:u(function(c,a,g,_){function i(){const F=a.yytext.indexOf("%%");if(F===0)return!1;if(F>0){const l=a.yytext.slice(0,F),B=a.yytext.slice(F);B&&c.lexer.unput(B),a.yytext=l}return!0}switch(u(i,"processId"),g){case 0:return 38;case 1:return 40;case 2:return 39;case 3:return 44;case 4:return 51;case 5:return 52;case 6:return 53;case 7:return 54;case 8:return 5;case 9:break;case 10:break;case 11:break;case 12:break;case 13:return this.pushState("SCALE"),17;case 14:return 18;case 15:this.popState();break;case 16:return this.begin("acc_title"),33;case 17:return this.popState(),"acc_title_value";case 18:return this.begin("acc_descr"),35;case 19:return this.popState(),"acc_descr_value";case 20:this.begin("acc_descr_multiline");break;case 21:this.popState();break;case 22:return"acc_descr_multiline_value";case 23:return this.pushState("CLASSDEF"),41;case 24:return this.popState(),this.pushState("CLASSDEFID"),"DEFAULT_CLASSDEF_ID";case 25:return this.popState(),this.pushState("CLASSDEFID"),42;case 26:return this.popState(),43;case 27:return this.pushState("CLASS"),48;case 28:return this.popState(),this.pushState("CLASS_STYLE"),49;case 29:return this.popState(),50;case 30:return this.pushState("STYLE"),45;case 31:return this.popState(),this.pushState("STYLEDEF_STYLES"),46;case 32:return this.popState(),47;case 33:return this.pushState("SCALE"),17;case 34:return 18;case 35:this.popState();break;case 36:this.pushState("STATE");break;case 37:return this.popState(),a.yytext=a.yytext.slice(0,-8).trim(),25;case 38:return this.popState(),a.yytext=a.yytext.slice(0,-8).trim(),26;case 39:return this.popState(),a.yytext=a.yytext.slice(0,-10).trim(),27;case 40:return this.popState(),a.yytext=a.yytext.slice(0,-8).trim(),25;case 41:return this.popState(),a.yytext=a.yytext.slice(0,-8).trim(),26;case 42:return this.popState(),a.yytext=a.yytext.slice(0,-10).trim(),27;case 43:return 51;case 44:return 52;case 45:return 53;case 46:return 54;case 47:this.pushState("STATE_STRING");break;case 48:return this.pushState("STATE_ID"),"AS";case 49:return i()?(this.popState(),"ID"):void 0;case 50:this.popState();break;case 51:return"STATE_DESCR";case 52:return 19;case 53:this.popState();break;case 54:return this.popState(),this.pushState("struct"),20;case 55:return this.popState(),21;case 56:break;case 57:return this.begin("NOTE"),29;case 58:return this.popState(),this.pushState("NOTE_ID"),59;case 59:return this.popState(),this.pushState("NOTE_ID"),60;case 60:this.popState(),this.pushState("FLOATING_NOTE");break;case 61:return this.popState(),this.pushState("FLOATING_NOTE_ID"),"AS";case 62:break;case 63:return"NOTE_TEXT";case 64:return i()?(this.popState(),"ID"):void 0;case 65:return i()?(this.popState(),this.pushState("NOTE_TEXT"),24):void 0;case 66:return this.popState(),a.yytext=a.yytext.substr(2).trim(),31;case 67:return this.popState(),a.yytext=a.yytext.slice(0,-8).trim(),31;case 68:return 6;case 69:return 6;case 70:return 16;case 71:return 57;case 72:return i()?24:void 0;case 73:return a.yytext=a.yytext.trim(),14;case 74:return 15;case 75:return 28;case 76:return 58;case 77:return 5;case 78:return"INVALID"}},"anonymous"),rules:[/^(?:click\b)/i,/^(?:href\b)/i,/^(?:"[^"]*")/i,/^(?:default\b)/i,/^(?:.*direction\s+TB[^\n]*)/i,/^(?:.*direction\s+BT[^\n]*)/i,/^(?:.*direction\s+RL[^\n]*)/i,/^(?:.*direction\s+LR[^\n]*)/i,/^(?:[\n]+)/i,/^(?:[\s]+)/i,/^(?:((?!\n)\s)+)/i,/^(?:#[^\n]*)/i,/^(?:%%(?!\{)[^\n]*)/i,/^(?:scale\s+)/i,/^(?:\d+)/i,/^(?:\s+width\b)/i,/^(?:accTitle\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*\{\s*)/i,/^(?:[\}])/i,/^(?:[^\}]*)/i,/^(?:classDef\s+)/i,/^(?:DEFAULT\s+)/i,/^(?:\w+\s+)/i,/^(?:[^\n]*)/i,/^(?:class\s+)/i,/^(?:(\w+)+((,\s*\w+)*))/i,/^(?:[^\n]*)/i,/^(?:style\s+)/i,/^(?:[\w,]+\s+)/i,/^(?:[^\n]*)/i,/^(?:scale\s+)/i,/^(?:\d+)/i,/^(?:\s+width\b)/i,/^(?:state\s+)/i,/^(?:.*<<fork>>)/i,/^(?:.*<<join>>)/i,/^(?:.*<<choice>>)/i,/^(?:.*\[\[fork\]\])/i,/^(?:.*\[\[join\]\])/i,/^(?:.*\[\[choice\]\])/i,/^(?:.*direction\s+TB[^\n]*)/i,/^(?:.*direction\s+BT[^\n]*)/i,/^(?:.*direction\s+RL[^\n]*)/i,/^(?:.*direction\s+LR[^\n]*)/i,/^(?:["])/i,/^(?:\s*as\s+)/i,/^(?:[^\n\{]*)/i,/^(?:["])/i,/^(?:[^"]*)/i,/^(?:[^\n\s\{]+)/i,/^(?:\n)/i,/^(?:\{)/i,/^(?:\})/i,/^(?:[\n])/i,/^(?:note\s+)/i,/^(?:left of\b)/i,/^(?:right of\b)/i,/^(?:")/i,/^(?:\s*as\s*)/i,/^(?:["])/i,/^(?:[^"]*)/i,/^(?:[^\n]*)/i,/^(?:\s*[^:\n\s\-]+)/i,/^(?:\s*:[^:\n;]+)/i,/^(?:[\s\S]*?\n\s*end note\b)/i,/^(?:stateDiagram\s+)/i,/^(?:stateDiagram-v2\s+)/i,/^(?:hide empty description\b)/i,/^(?:\[\*\])/i,/^(?:[^:\n\s\-\{]+)/i,/^(?:\s*:(?:[^:\n;]|:[^:\n;])+)/i,/^(?:-->)/i,/^(?:--)/i,/^(?::::)/i,/^(?:$)/i,/^(?:.)/i],conditions:{LINE:{rules:[10,11,12],inclusive:!1},struct:{rules:[10,11,12,23,27,30,36,43,44,45,46,55,56,57,71,72,73,74,75,76],inclusive:!1},FLOATING_NOTE_ID:{rules:[64],inclusive:!1},FLOATING_NOTE:{rules:[61,62,63],inclusive:!1},NOTE_TEXT:{rules:[66,67],inclusive:!1},NOTE_ID:{rules:[65],inclusive:!1},NOTE:{rules:[58,59,60],inclusive:!1},STYLEDEF_STYLEOPTS:{rules:[],inclusive:!1},STYLEDEF_STYLES:{rules:[32],inclusive:!1},STYLE_IDS:{rules:[],inclusive:!1},STYLE:{rules:[31],inclusive:!1},CLASS_STYLE:{rules:[29],inclusive:!1},CLASS:{rules:[28],inclusive:!1},CLASSDEFID:{rules:[26],inclusive:!1},CLASSDEF:{rules:[24,25],inclusive:!1},acc_descr_multiline:{rules:[21,22],inclusive:!1},acc_descr:{rules:[19],inclusive:!1},acc_title:{rules:[17],inclusive:!1},SCALE:{rules:[14,15,34,35],inclusive:!1},ALIAS:{rules:[],inclusive:!1},STATE_ID:{rules:[49],inclusive:!1},STATE_STRING:{rules:[50,51],inclusive:!1},FORK_STATE:{rules:[],inclusive:!1},STATE:{rules:[10,11,12,37,38,39,40,41,42,47,48,52,53,54],inclusive:!1},ID:{rules:[10,11,12],inclusive:!1},INITIAL:{rules:[0,1,2,3,4,5,6,7,8,9,11,12,13,16,18,20,23,27,30,33,36,54,57,68,69,70,71,72,73,74,76,77,78],inclusive:!0}}};return Y})();gt.lexer=qt;function ht(){this.yy={}}return u(ht,"Parser"),ht.prototype=gt,gt.Parser=ht,new ht})();vt.parser=vt;var Ye=vt,de="TB",Bt="TB",Ot="dir",J="state",X="root",Ct="relation",fe="classDef",pe="style",Se="applyClass",et="default",Gt="divider",Yt="fill:none",Vt="fill: #333",Mt="c",Ut="markdown",Wt="normal",kt="rect",Dt="rectWithTitle",ye="stateStart",ge="stateEnd",Nt="divider",Rt="roundedWithTitle",Te="note",Ee="noteGroup",st="statediagram",_e="state",me=`${st}-${_e}`,jt="transition",be="note",ke="note-edge",De=`${jt} ${ke}`,ve=`${st}-${be}`,Ce="cluster",Ae=`${st}-${Ce}`,xe="cluster-alt",Le=`${st}-${xe}`,Ht="parent",zt="note",Ie="state",At="----",Oe=`${At}${zt}`,wt=`${At}${Ht}`,Kt=u((t,e=Bt)=>{if(!t.doc)return e;let s=e;for(const n of t.doc)n.stmt==="dir"&&(s=n.value);return s},"getDir"),Ne=u(function(t,e){return e.db.getClasses()},"getClasses"),Re=u(async function(t,e,s,n){b.info("REF0:"),b.info("Drawing state diagram (v2)",e);const{securityLevel:r,state:d,layout:f}=w();n.db.extract(n.db.getRootDocV2());const S=n.db.getData(),p=te(e,r);S.type=n.type,S.layoutAlgorithm=f,S.nodeSpacing=d?.nodeSpacing||50,S.rankSpacing=d?.rankSpacing||50,w().look==="neo"?S.markers=["barbNeo"]:S.markers=["barb"],S.diagramId=e,await se(S,p);const E=8;try{(typeof n.db.getLinks=="function"?n.db.getLinks():new Map).forEach((x,D)=>{const h=typeof D=="string"?D:typeof D?.id=="string"?D.id:"";if(!h){b.warn("⚠️ Invalid or missing stateId from key:",JSON.stringify(D));return}const L=p.node()?.querySelectorAll("g");let v;if(L?.forEach(O=>{O.textContent?.trim()===h&&(v=O)}),!v){b.warn("⚠️ Could not find node matching text:",h);return}const $=v.parentNode;if(!$){b.warn("⚠️ Node has no parent, cannot wrap:",h);return}const I=document.createElementNS("http://www.w3.org/2000/svg","a"),P=x.url.replace(/^"+|"+$/g,"");if(I.setAttributeNS("http://www.w3.org/1999/xlink","xlink:href",P),I.setAttribute("target","_blank"),x.tooltip){const O=x.tooltip.replace(/^"+|"+$/g,"");I.setAttribute("title",O)}$.replaceChild(I,v),I.appendChild(v),b.info("🔗 Wrapped node in <a> tag for:",h,x.url)})}catch(m){b.error("❌ Error injecting clickable links:",m)}ie.insertTitle(p,"statediagramTitleText",d?.titleTopMargin??25,n.db.getDiagramTitle()),ee(p,E,st,d?.useMaxWidth??!0)},"draw"),Ve={getClasses:Ne,draw:Re,getDir:Kt},St=new Map,V=0;function yt(t="",e=0,s="",n=At){const r=s!==null&&s.length>0?`${n}${s}`:"";return`${Ie}-${t}${r}-${e}`}u(yt,"stateDomId");var we=u((t,e,s,n,r,d,f,S)=>{b.trace("items",e),e.forEach(p=>{switch(p.stmt){case J:tt(t,p,s,n,r,d,f,S);break;case et:tt(t,p,s,n,r,d,f,S);break;case Ct:{tt(t,p.state1,s,n,r,d,f,S),tt(t,p.state2,s,n,r,d,f,S);const T=f==="neo",E={id:"edge"+V,start:p.state1.id,end:p.state2.id,arrowhead:"normal",arrowTypeEnd:T?"arrow_barb_neo":"arrow_barb",style:Yt,labelStyle:"",label:W.sanitizeText(p.description??"",w()),arrowheadStyle:Vt,labelpos:Mt,labelType:Ut,thickness:Wt,classes:jt,look:f};r.push(E),V++}break}})},"setupDoc"),$t=u((t,e=Bt)=>{let s=e;if(t.doc)for(const n of t.doc)n.stmt==="dir"&&(s=n.value);return s},"getDir");function Z(t,e,s){if(!e.id||e.id==="</join></fork>"||e.id==="</choice>")return;e.cssClasses&&(Array.isArray(e.cssCompiledStyles)||(e.cssCompiledStyles=[]),e.cssClasses.split(" ").forEach(r=>{const d=s.get(r);d&&(e.cssCompiledStyles=[...e.cssCompiledStyles??[],...d.styles])}));const n=t.find(r=>r.id===e.id);n?Object.assign(n,e):t.push(e)}u(Z,"insertOrUpdateNode");function Xt(t){return t?.classes?.join(" ")??""}u(Xt,"getClassesFromDbInfo");function Jt(t){return t?.styles??[]}u(Jt,"getStylesFromDbInfo");var tt=u((t,e,s,n,r,d,f,S)=>{const p=e.id,T=s.get(p),E=Xt(T),m=Jt(T),x=w();if(b.info("dataFetcher parsedItem",e,T,m),p!=="root"){let D=kt;e.start===!0?D=ye:e.start===!1&&(D=ge),e.type!==et&&(D=e.type),St.get(p)||St.set(p,{id:p,shape:D,description:W.sanitizeText(p,x),cssClasses:`${E} ${me}`,cssStyles:m});const h=St.get(p);e.description&&(Array.isArray(h.description)?(h.shape=Dt,h.description.push(e.description)):h.description?.length&&h.description.length>0?(h.shape=Dt,h.description===p?h.description=[e.description]:h.description=[h.description,e.description]):(h.shape=kt,h.description=e.description),h.description=W.sanitizeTextOrArray(h.description,x)),h.description?.length===1&&h.shape===Dt&&(h.type==="group"?h.shape=Rt:h.shape=kt),!h.type&&e.doc&&(b.info("Setting cluster for XCX",p,$t(e)),h.type="group",h.isGroup=!0,h.dir=$t(e),h.shape=e.type===Gt?Nt:Rt,h.cssClasses=`${h.cssClasses} ${Ae} ${d?Le:""}`);const L={labelStyle:"",shape:h.shape,label:h.description,cssClasses:h.cssClasses,cssCompiledStyles:[],cssStyles:h.cssStyles,id:p,dir:h.dir,domId:yt(p,V),type:h.type,isGroup:h.type==="group",padding:8,rx:10,ry:10,look:f,labelType:"markdown"};if(L.shape===Nt&&(L.label=""),t&&t.id!=="root"&&(b.trace("Setting node ",p," to be child of its parent ",t.id),L.parentId=t.id),L.centerLabel=!0,e.note){const v={labelStyle:"",shape:Te,label:e.note.text,labelType:"markdown",cssClasses:ve,cssStyles:[],cssCompiledStyles:[],id:p+Oe+"-"+V,domId:yt(p,V,zt),type:h.type,isGroup:h.type==="group",padding:x.flowchart?.padding,look:f,position:e.note.position},$=p+wt,I={labelStyle:"",shape:Ee,label:e.note.text,cssClasses:h.cssClasses,cssStyles:[],id:p+wt,domId:yt(p,V,Ht),type:"group",isGroup:!0,padding:16,look:f,position:e.note.position};V++,I.id=$,v.parentId=$,Z(n,I,S),Z(n,v,S),Z(n,L,S);let P=p,O=v.id;e.note.position==="left of"&&(P=v.id,O=p),r.push({id:P+"-"+O,start:P,end:O,arrowhead:"none",arrowTypeEnd:"",style:Yt,labelStyle:"",classes:De,arrowheadStyle:Vt,labelpos:Mt,labelType:Ut,thickness:Wt,look:f})}else Z(n,L,S)}e.doc&&(b.trace("Adding nodes children "),we(e,e.doc,s,n,r,!d,f,S))},"dataFetcher"),$e=u(()=>{St.clear(),V=0},"reset"),C={START_NODE:"[*]",START_TYPE:"start",END_NODE:"[*]",END_TYPE:"end",COLOR_KEYWORD:"color",FILL_KEYWORD:"fill",BG_FILL:"bgFill",STYLECLASS_SEP:","},Pt=u(()=>new Map,"newClassesList"),Ft=u(()=>({relations:[],states:new Map,documents:{}}),"newDoc"),pt=u(t=>JSON.parse(JSON.stringify(t)),"clone"),j,Me=(j=class{constructor(e){this.version=e,this.nodes=[],this.edges=[],this.rootDoc=[],this.classes=Pt(),this.documents={root:Ft()},this.currentDocument=this.documents.root,this.startEndCount=0,this.dividerCnt=0,this.links=new Map,this.getAccTitle=re,this.setAccTitle=ae,this.getAccDescription=ne,this.setAccDescription=oe,this.setDiagramTitle=le,this.getDiagramTitle=ce,this.clear(),this.setRootDoc=this.setRootDoc.bind(this),this.getDividerId=this.getDividerId.bind(this),this.setDirection=this.setDirection.bind(this),this.trimColon=this.trimColon.bind(this)}extract(e){this.clear(!0);for(const r of Array.isArray(e)?e:e.doc)switch(r.stmt){case J:this.addState(r.id.trim(),r.type,r.doc,r.description,r.note);break;case Ct:this.addRelation(r.state1,r.state2,r.description);break;case fe:this.addStyleClass(r.id.trim(),r.classes);break;case pe:this.handleStyleDef(r);break;case Se:this.setCssClass(r.id.trim(),r.styleClass);break;case"click":this.addLink(r.id,r.url,r.tooltip);break}const s=this.getStates(),n=w();$e(),tt(void 0,this.getRootDocV2(),s,this.nodes,this.edges,!0,n.look,this.classes);for(const r of this.nodes)if(Array.isArray(r.label)){if(r.description=r.label.slice(1),r.isGroup&&r.description.length>0)throw new Error(`Group nodes can only have label. Remove the additional description for node [${r.id}]`);r.label=r.label[0]}}handleStyleDef(e){const s=e.id.trim().split(","),n=e.styleClass.split(",");for(const r of s){let d=this.getState(r);if(!d){const f=r.trim();this.addState(f),d=this.getState(f)}d&&(d.styles=n.map(f=>f.replace(/;/g,"")?.trim()))}}setRootDoc(e){b.info("Setting root doc",e),this.rootDoc=e,this.version===1?this.extract(e):this.extract(this.getRootDocV2())}docTranslator(e,s,n){if(s.stmt===Ct){this.docTranslator(e,s.state1,!0),this.docTranslator(e,s.state2,!1);return}if(s.stmt===J&&(s.id===C.START_NODE?(s.id=e.id+(n?"_start":"_end"),s.start=n):s.id=s.id.trim()),s.stmt!==X&&s.stmt!==J||!s.doc)return;const r=[];let d=[];for(const f of s.doc)if(f.type===Gt){const S=pt(f);S.doc=pt(d),r.push(S),d=[]}else d.push(f);if(r.length>0&&d.length>0){const f={stmt:J,id:he(),type:"divider",doc:pt(d)};r.push(pt(f)),s.doc=r}s.doc.forEach(f=>this.docTranslator(s,f,!0))}getRootDocV2(){return this.docTranslator({id:X,stmt:X},{id:X,stmt:X,doc:this.rootDoc},!0),{id:X,doc:this.rootDoc}}addState(e,s=et,n=void 0,r=void 0,d=void 0,f=void 0,S=void 0,p=void 0){const T=e?.trim();if(!this.currentDocument.states.has(T))b.info("Adding state ",T,r),this.currentDocument.states.set(T,{stmt:J,id:T,descriptions:[],type:s,doc:n,note:d,classes:[],styles:[],textStyles:[]});else{const E=this.currentDocument.states.get(T);if(!E)throw new Error(`State not found: ${T}`);E.doc||(E.doc=n),E.type||(E.type=s)}if(r&&(b.info("Setting state description",T,r),(Array.isArray(r)?r:[r]).forEach(m=>this.addDescription(T,m.trim()))),d){const E=this.currentDocument.states.get(T);if(!E)throw new Error(`State not found: ${T}`);E.note=d,E.note.text=W.sanitizeText(E.note.text,w())}f&&(b.info("Setting state classes",T,f),(Array.isArray(f)?f:[f]).forEach(m=>this.setCssClass(T,m.trim()))),S&&(b.info("Setting state styles",T,S),(Array.isArray(S)?S:[S]).forEach(m=>this.setStyle(T,m.trim()))),p&&(b.info("Setting state styles",T,S),(Array.isArray(p)?p:[p]).forEach(m=>this.setTextStyle(T,m.trim())))}clear(e){this.nodes=[],this.edges=[],this.documents={root:Ft()},this.currentDocument=this.documents.root,this.startEndCount=0,this.classes=Pt(),e||(this.links=new Map,ue())}getState(e){return this.currentDocument.states.get(e)}getStates(){return this.currentDocument.states}logDocuments(){b.info("Documents = ",this.documents)}getRelations(){return this.currentDocument.relations}addLink(e,s,n){this.links.set(e,{url:s,tooltip:n}),b.warn("Adding link",e,s,n)}getLinks(){return this.links}startIdIfNeeded(e=""){return e===C.START_NODE?(this.startEndCount++,`${C.START_TYPE}${this.startEndCount}`):e}startTypeIfNeeded(e="",s=et){return e===C.START_NODE?C.START_TYPE:s}endIdIfNeeded(e=""){return e===C.END_NODE?(this.startEndCount++,`${C.END_TYPE}${this.startEndCount}`):e}endTypeIfNeeded(e="",s=et){return e===C.END_NODE?C.END_TYPE:s}addRelationObjs(e,s,n=""){const r=this.startIdIfNeeded(e.id.trim()),d=this.startTypeIfNeeded(e.id.trim(),e.type),f=this.startIdIfNeeded(s.id.trim()),S=this.startTypeIfNeeded(s.id.trim(),s.type);this.addState(r,d,e.doc,e.description,e.note,e.classes,e.styles,e.textStyles),this.addState(f,S,s.doc,s.description,s.note,s.classes,s.styles,s.textStyles),this.currentDocument.relations.push({id1:r,id2:f,relationTitle:W.sanitizeText(n,w())})}addRelation(e,s,n){if(typeof e=="object"&&typeof s=="object")this.addRelationObjs(e,s,n);else if(typeof e=="string"&&typeof s=="string"){const r=this.startIdIfNeeded(e.trim()),d=this.startTypeIfNeeded(e),f=this.endIdIfNeeded(s.trim()),S=this.endTypeIfNeeded(s);this.addState(r,d),this.addState(f,S),this.currentDocument.relations.push({id1:r,id2:f,relationTitle:n?W.sanitizeText(n,w()):void 0})}}addDescription(e,s){const n=this.currentDocument.states.get(e),r=s.startsWith(":")?s.replace(":","").trim():s;n?.descriptions?.push(W.sanitizeText(r,w()))}cleanupLabel(e){return e.startsWith(":")?e.slice(2).trim():e.trim()}getDividerId(){return this.dividerCnt++,`divider-id-${this.dividerCnt}`}addStyleClass(e,s=""){this.classes.has(e)||this.classes.set(e,{id:e,styles:[],textStyles:[]});const n=this.classes.get(e);s&&n&&s.split(C.STYLECLASS_SEP).forEach(r=>{const d=r.replace(/([^;]*);/,"$1").trim();if(RegExp(C.COLOR_KEYWORD).exec(r)){const S=d.replace(C.FILL_KEYWORD,C.BG_FILL).replace(C.COLOR_KEYWORD,C.FILL_KEYWORD);n.textStyles.push(S)}n.styles.push(d)})}getClasses(){return this.classes}setCssClass(e,s){e.split(",").forEach(n=>{let r=this.getState(n);if(!r){const d=n.trim();this.addState(d),r=this.getState(d)}r?.classes?.push(s)})}setStyle(e,s){this.getState(e)?.styles?.push(s)}setTextStyle(e,s){this.getState(e)?.textStyles?.push(s)}getDirectionStatement(){return this.rootDoc.find(e=>e.stmt===Ot)}getDirection(){return this.getDirectionStatement()?.value??de}setDirection(e){const s=this.getDirectionStatement();s?s.value=e:this.rootDoc.unshift({stmt:Ot,value:e})}trimColon(e){return e.startsWith(":")?e.slice(1).trim():e.trim()}getData(){const e=w();return{nodes:this.nodes,edges:this.edges,other:{},config:e,direction:Kt(this.getRootDocV2())}}getConfig(){return w().state}},u(j,"StateDB"),j.relationType={AGGREGATION:0,EXTENSION:1,COMPOSITION:2,DEPENDENCY:3},j),Pe=u(t=>`
defs [id$="-barbEnd"] {
    fill: ${t.transitionColor};
    stroke: ${t.transitionColor};
  }
g.stateGroup text {
  fill: ${t.nodeBorder};
  stroke: none;
  font-size: 10px;
}
g.stateGroup text {
  fill: ${t.textColor};
  stroke: none;
  font-size: 10px;

}
g.stateGroup .state-title {
  font-weight: bolder;
  fill: ${t.stateLabelColor};
}

g.stateGroup rect {
  fill: ${t.mainBkg};
  stroke: ${t.nodeBorder};
}

g.stateGroup line {
  stroke: ${t.lineColor};
  stroke-width: ${t.strokeWidth||1};
}

.transition {
  stroke: ${t.transitionColor};
  stroke-width: ${t.strokeWidth||1};
  fill: none;
}

.stateGroup .composit {
  fill: ${t.background};
  border-bottom: 1px
}

.stateGroup .alt-composit {
  fill: #e0e0e0;
  border-bottom: 1px
}

.state-note {
  stroke: ${t.noteBorderColor};
  fill: ${t.noteBkgColor};

  text {
    fill: ${t.noteTextColor};
    stroke: none;
    font-size: 10px;
  }
}

.stateLabel .box {
  stroke: none;
  stroke-width: 0;
  fill: ${t.mainBkg};
  opacity: 0.5;
}

.edgeLabel .label rect {
  fill: ${t.labelBackgroundColor};
  opacity: 0.5;
}
.edgeLabel {
  background-color: ${t.edgeLabelBackground};
  p {
    background-color: ${t.edgeLabelBackground};
  }
  rect {
    opacity: 0.5;
    background-color: ${t.edgeLabelBackground};
    fill: ${t.edgeLabelBackground};
  }
  text-align: center;
}
.edgeLabel .label text {
  fill: ${t.transitionLabelColor||t.tertiaryTextColor};
}
.label div .edgeLabel {
  color: ${t.transitionLabelColor||t.tertiaryTextColor};
}

.stateLabel text {
  fill: ${t.stateLabelColor};
  font-size: 10px;
  font-weight: bold;
}

.node circle.state-start {
  fill: ${t.specialStateColor};
  stroke: ${t.specialStateColor};
}

.node .fork-join {
  fill: ${t.specialStateColor};
  stroke: ${t.specialStateColor};
}

.node circle.state-end {
  fill: ${t.innerEndBackground};
  stroke: ${t.background};
  stroke-width: 1.5
}
.end-state-inner {
  fill: ${t.compositeBackground||t.background};
  // stroke: ${t.background};
  stroke-width: 1.5
}

.node rect {
  fill: ${t.stateBkg||t.mainBkg};
  stroke: ${t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth||1}px;
}
.node polygon {
  fill: ${t.mainBkg};
  stroke: ${t.stateBorder||t.nodeBorder};;
  stroke-width: ${t.strokeWidth||1}px;
}
[id$="-barbEnd"] {
  fill: ${t.lineColor};
}

.statediagram-cluster rect {
  fill: ${t.compositeTitleBackground};
  stroke: ${t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth||1}px;
}

.cluster-label, .nodeLabel {
  color: ${t.stateLabelColor};
  // line-height: 1;
}

.statediagram-cluster rect.outer {
  rx: 5px;
  ry: 5px;
}
.statediagram-state .divider {
  stroke: ${t.stateBorder||t.nodeBorder};
}

.statediagram-state .title-state {
  rx: 5px;
  ry: 5px;
}
.statediagram-cluster.statediagram-cluster .inner {
  fill: ${t.compositeBackground||t.background};
}
.statediagram-cluster.statediagram-cluster-alt .inner {
  fill: ${t.altBackground?t.altBackground:"#efefef"};
}

.statediagram-cluster .inner {
  rx:0;
  ry:0;
}

.statediagram-state rect.basic {
  rx: 5px;
  ry: 5px;
}
.statediagram-state rect.divider {
  stroke-dasharray: 10,10;
  fill: ${t.altBackground?t.altBackground:"#efefef"};
}

.note-edge {
  stroke-dasharray: 5;
}

.statediagram-note rect {
  fill: ${t.noteBkgColor};
  stroke: ${t.noteBorderColor};
  stroke-width: 1px;
  rx: 0;
  ry: 0;
}
.statediagram-note rect {
  fill: ${t.noteBkgColor};
  stroke: ${t.noteBorderColor};
  stroke-width: 1px;
  rx: 0;
  ry: 0;
}

.statediagram-note text {
  fill: ${t.noteTextColor};
}

.statediagram-note .nodeLabel {
  color: ${t.noteTextColor};
}
.statediagram .edgeLabel {
  color: red; // ${t.noteTextColor};
}

[id$="-dependencyStart"], [id$="-dependencyEnd"] {
  fill: ${t.lineColor};
  stroke: ${t.lineColor};
  stroke-width: ${t.strokeWidth||1};
}

.statediagramTitleText {
  text-anchor: middle;
  font-size: 18px;
  fill: ${t.textColor};
}

[data-look="neo"].statediagram-cluster rect {
  fill: ${t.mainBkg};
  stroke: ${t.useGradient?"url("+t.svgId+"-gradient)":t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth??1};
}
[data-look="neo"].statediagram-cluster rect.outer {
  rx: ${t.radius}px;
  ry: ${t.radius}px;
  filter: ${t.dropShadow?t.dropShadow.replace("url(#drop-shadow)",`url(${t.svgId}-drop-shadow)`):"none"}
}
`,"getStyles"),Ue=Pe;export{Me as S,Ye as a,Ve as b,Ue as s};
