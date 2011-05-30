/************************************************************************************************************
Ajax dynamic content
Copyright (C) 2006  DTHMLGoodies.com, Alf Magne Kalleland

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA

Dhtmlgoodies.com., hereby disclaims all copyright interest in this script
written by Alf Magne Kalleland.

Alf Magne Kalleland, 2006
Owner of DHTMLgoodies.com


************************************************************************************************************/	

var enableCache = true;
var jsCache = new Array();

var dynamicContent_ajaxObjects = new Array();

function ajax_showContent(divId,ajaxIndex,url,callbackOnComplete)
{
	var targetObj = document.getElementById(divId);
	targetObj.innerHTML = dynamicContent_ajaxObjects[ajaxIndex].response;
	if(enableCache){
		jsCache[url] = 	dynamicContent_ajaxObjects[ajaxIndex].response;
	}
	dynamicContent_ajaxObjects[ajaxIndex] = false;
	
	ajax_parseJs(targetObj);
	
	if(callbackOnComplete) {
		executeCallback(callbackOnComplete);
	}
}

function executeCallback(callbackString) {
	if(callbackString.indexOf('(')==-1) {
		callbackString = callbackString + '()';
	}
	try{
		eval(callbackString);
	}catch(e){

	}
	
	
}

function ajax_loadContent(divId,url,onLoad,callbackOnComplete)
{
    var url_orig = url; // for knowing the url in showContent
	if(enableCache && jsCache[url]){
		document.getElementById(divId).innerHTML = jsCache[url];
		ajax_parseJs(document.getElementById(divId))
		evaluateCss(document.getElementById(divId))
		if(callbackOnComplete) {
			executeCallback(callbackOnComplete);
		}		
		return;
	}
	
	var ajaxIndex = dynamicContent_ajaxObjects.length;
    // Print some content until loading ?
    if (onLoad){
	    document.getElementById(divId).innerHTML = onLoad;
    }
	//document.getElementById(divId).innerHTML = 'Loading content - please wait';
	//document.getElementById(divId).innerHTML = '';
	dynamicContent_ajaxObjects[ajaxIndex] = new sack();
	
	if(url.indexOf('?')>=0){
		dynamicContent_ajaxObjects[ajaxIndex].method='GET';
		var string = url.substring(url.indexOf('?'));
		url = url.replace(string,'');
		string = string.replace('?','');
		var items = string.split(/&/g);
		for(var no=0;no<items.length;no++){
			var tokens = items[no].split('=');
			if(tokens.length==2){
				dynamicContent_ajaxObjects[ajaxIndex].setVar(tokens[0],tokens[1]);
			}	
		}	
		url = url.replace(string,'');
	}

	
	dynamicContent_ajaxObjects[ajaxIndex].requestFile = url;	// Specifying which file to get
	dynamicContent_ajaxObjects[ajaxIndex].onCompletion = function(){ ajax_showContent(divId,ajaxIndex,url_orig,callbackOnComplete); };	// Specify function that will be executed after file has been found
	dynamicContent_ajaxObjects[ajaxIndex].runAJAX();		// Execute AJAX function	
	
	
}

function ajax_parseJs(obj)
{
	var scriptTags = obj.getElementsByTagName('SCRIPT');
	var string = '';
	var jsCode = '';
	for(var no=0;no<scriptTags.length;no++){	
		if(scriptTags[no].src){
	        var head = document.getElementsByTagName("head")[0];
	        var scriptObj = document.createElement("script");
	
	        scriptObj.setAttribute("type", "text/javascript");
	        scriptObj.setAttribute("src", scriptTags[no].src);  	
		}else{
			if(navigator.userAgent.toLowerCase().indexOf('opera')>=0){
				jsCode = jsCode + scriptTags[no].text + '\n';
			}
			else
				jsCode = jsCode + scriptTags[no].innerHTML;	
		}
		
	}

	if(jsCode)ajax_installScript(jsCode);
}


function ajax_installScript(script)
{		
    if (!script)
        return;		
    if (window.execScript){        	
    	window.execScript(script)
    }else if(window.jQuery && jQuery.browser.safari){ // safari detection in jQuery
        window.setTimeout(script,0);
    }else{        	
        window.setTimeout( script, 0 );
    } 
}	
	
	
function evaluateCss(obj)
{
	var cssTags = obj.getElementsByTagName('STYLE');
	var head = document.getElementsByTagName('HEAD')[0];
	for(var no=0;no<cssTags.length;no++){
		head.appendChild(cssTags[no]);
	}	
}





/************************************************************************************************************
(C) www.dhtmlgoodies.com, November 2005

This is a script from www.dhtmlgoodies.com. You will find this and a lot of other scripts at our website.   

Terms of use:
You are free to use this script as long as the copyright message is kept intact. However, you may not
redistribute, sell or repost it without our permission.

Thank you!

www.dhtmlgoodies.com
Alf Magne Kalleland

************************************************************************************************************/

var dhtmlgoodies_slideSpeed = 5;   // Higher value = faster
var dhtmlgoodies_timer = 10;    // Lower value = faster

var objectIdToSlideDown = false;
var dhtmlgoodies_activeId = false;
var dhtmlgoodies_slideInProgress = false;

// temporary variable for knowing if there other elements like boxes to display or hide (like advanced_tab)
var swap_more = false;
var set_cookie = false;
function showHideContent(e, inputId, action)
{
    if(dhtmlgoodies_slideInProgress)return;
    dhtmlgoodies_slideInProgress = true;
    if(!inputId)inputId = this.id;
    inputId = inputId + '';
    var numericId = inputId.replace(/[^0-9]/g,'');
    var answerDiv = document.getElementById('dhtmlgoodies_a' + numericId);
//    var content = document.getElementById('dhtmlgoodies_ac' + numericId);

if ( !action ||
    (action &&
        ( action=='show' && (!answerDiv.style.display || answerDiv.style.display=='none') )
     || ( action=='hide' && (answerDiv.style.display && answerDiv.style.display=='block') )
    )
){


    var expandImg = document.getElementById('dhtmlgoodies_question_expandImg');
    if(expandImg){
        
        if (!answerDiv.style.display || answerDiv.style.display=='none'){
            expandImg.src = 'img/icon_collapse.gif';
        }else{
            expandImg.src = 'img/icon_expand.gif';
        }
    }

    objectIdToSlideDown = false;
    
    if(!answerDiv.style.display || answerDiv.style.display=='none'){        
        if(dhtmlgoodies_activeId &&  dhtmlgoodies_activeId!=numericId){         
            objectIdToSlideDown = numericId;
            slideContent(dhtmlgoodies_activeId,(dhtmlgoodies_slideSpeed*-1));
        }else{
            
            answerDiv.style.display='block';
            answerDiv.style.visibility = 'visible';

            // Set cookie to "open"
            if (set_cookie && set_cookie!=''){
                //alert( swap_more + "open");
                createCookie(swap_more, "open", 0);
            }
            
            slideContent(numericId,dhtmlgoodies_slideSpeed);
        }
    }else{
        // Set cookie to "closed"
        if (set_cookie && set_cookie!=''){
            //alert( swap_more + "closed");
            createCookie(swap_more, "closed", 0);
        }
        slideContent(numericId,(dhtmlgoodies_slideSpeed*-1));
        dhtmlgoodies_activeId = false;
    }   

    // This just works when there is 1 TAB ! when using more than 1 tab, code must be redone...
//    var questionDiv = document.getElementById('dhtmlgoodies_q' + numericId);
//    var tab = questionDiv.parentNode.parentNode.className;
    if (swap_more && swap_more!=''){
        swap_advanced(swap_more);
    }


}else{
    dhtmlgoodies_slideInProgress = false;
}


}

function slideContent(inputId,direction)
{
    
    var obj =document.getElementById('dhtmlgoodies_a' + inputId);
    var contentObj = document.getElementById('dhtmlgoodies_ac' + inputId);
    height = obj.clientHeight;
    if(height==0)height = obj.offsetHeight;
    height = height + direction;
    rerunFunction = true;
    if(height>contentObj.offsetHeight){
        height = contentObj.offsetHeight;
        rerunFunction = false;
    }
    if(height<=1){
        height = 1;
        rerunFunction = false;
    }

    obj.style.height = height + 'px';
    var topPos = height - contentObj.offsetHeight;
    if(topPos>0)topPos=0;
    contentObj.style.top = topPos + 'px';
    if(rerunFunction){
        setTimeout('slideContent(' + inputId + ',' + direction + ')',dhtmlgoodies_timer);
    }else{
        if(height<=1){
            obj.style.display='none'; 
            if(objectIdToSlideDown && objectIdToSlideDown!=inputId){
                document.getElementById('dhtmlgoodies_a' + objectIdToSlideDown).style.display='block';
                document.getElementById('dhtmlgoodies_a' + objectIdToSlideDown).style.visibility='visible';
                slideContent(objectIdToSlideDown,dhtmlgoodies_slideSpeed);              
            }else{
                dhtmlgoodies_slideInProgress = false;
            }
        }else{
            dhtmlgoodies_activeId = inputId;
            dhtmlgoodies_slideInProgress = false;
        }
    }
}



function initShowHideDivs(name, mouse_over, cookie)
{
    swap_more = name;
    if(cookie){
        set_cookie = cookie;
    }
    var divs = document.getElementsByTagName('DIV');
    var divCounter = 1;
    for(var no=0;no<divs.length;no++){
        if(divs[no].className=='dhtmlgoodies_question'){
            divs[no].onclick = showHideContent;
            if(mouse_over){
                divs[no].onmouseover = showHideContent;
            }
            divs[no].id = 'dhtmlgoodies_q'+divCounter;

            /* test for dynamic content...
            if (expand){
                var myH2   = document.createElement("h2");
                var myspan = document.createElement("span");
                var myimg  = document.createElement("img");
                myimg.src = 'img/icon_expand.gif';
                myimg.id  = 'dhtmlgoodies_expand'+divCounter;
                var mytext = document.createTextNode(expand);
                myspan.appendChild(myimg);
                myspan.appendChild(mytext);
                myH2.appendChild(myspan);
                divs[no].appendChild(myH2);
            }
            */

            var answer = divs[no].nextSibling;
            while(answer && answer.tagName!='DIV'){
                answer = answer.nextSibling;
            }
            answer.id = 'dhtmlgoodies_a'+divCounter;    
            contentDiv = answer.getElementsByTagName('DIV')[0];
            contentDiv.style.top = 0 - contentDiv.offsetHeight + 'px';  
            contentDiv.className='dhtmlgoodies_answer_content';
            contentDiv.id = 'dhtmlgoodies_ac' + divCounter;
            answer.style.display='none';
            answer.style.height='1px';

            // read cookie, display TAB when it was already open
            var cookie_status = readCookie(name);
            if (cookie_status && cookie_status=="open") {
                contentDiv.parentNode.style.display     = 'block';
                contentDiv.parentNode.style.visibility  = 'visible';
                contentDiv.parentNode.style.height      = contentDiv.offsetHeight + "px";
                contentDiv.style.top = "0px";
                // Change also expand Image
                var expandImg = document.getElementById('dhtmlgoodies_question_expandImg');
                expandImg.src = 'img/icon_collapse.gif';
            }
            

            divCounter++;
        }       
    }   
}


