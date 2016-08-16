// Functions for altering elements and element properties in HTML DOM
Element.prototype.remove = function() {
    this.parentElement.removeChild(this);
}

Element.prototype.hasClass = function(c) {
    return (' ' + this.className + ' ').indexOf(' ' + c + ' ') > -1;
}

Element.prototype.addClass = function(c) {
	if (this.classList)
		this.classList.add(c);
	else if (!this.hasClass(c))
		this.className += " " + c;
}

Element.prototype.removeClass = function(c) {
	if (this.classList)
		this.classList.remove(c);
	else if (this.hasClass(c)) {
		var reg = new RegExp('(\\s|^)' + c + '(\\s|$)');
		this.className=this.className.replace(reg, ' ');
	}
}

//Builds highchart graph for VSPP Report
function vsppGraph(cid) {
	this.cid = cid;
	if(!document.getElementById("dtpData" + this.cid))
		return;
	var list = document.getElementById("dtpData" + this.cid).children;
	var dtpSeries = [];
	var next;
	for (var i = 0; i < list.length; i++) {
		next = list[i].innerHTML.split(",");
		dtpSeries.push([Date.parse(next[0]), parseInt(next[1])]);
	}

	var usrList = document.getElementById("userData" + this.cid).children;
	var usrSeries = [];
	for (var i = 0; i < usrList.length; i++) {
		next = usrList[i].innerHTML.split(",");
		usrSeries.push([Date.parse(next[0]), parseInt(next[1])]);
	}

    $('#VSPPGraph').highcharts({
        chart: {
            type: 'line'
        },
        title: {
            text: 'Desktop and User counts for Customer ' + this.cid
        },
        xAxis: {
        	type: 'datetime',
        	dateTimeLabelFormats: {
                month: '%e. %b',
                year: '%b'
            },
            title: {
                text: 'Date'
            }
        },
        yAxis: [{
            min: 0,
            title: {
                text: 'Desktops'
            }
        }/*, {
            title: {
                text: 'Profit (millions)'
            },
            opposite: true
        }*/],
        legend: {
            shadow: true
        },
        tooltip: {
            shared: true
        },
        plotOptions: {
            column: {
                grouping: false,
                shadow: false,
                borderWidth: 0
            }
        },
        series: [{
            name: 'Provisioned Desktops',
            color: 'rgba(165,170,217,1)',
            data: dtpSeries
        }, {
        	name: 'Concurrent Users',
        	data: usrSeries
        }]
    });
}

//Builds highchart graph for provisioning report
function provGraph(cid) {
	this.cid = cid;
	console.log("Making Graph");
	console.log(document.getElementById("provData" + this.cid));
	if(!document.getElementById("provData" + this.cid))
		return;
	var list = document.getElementById("provData" + this.cid).children;
	var mySeries = [];
	var next;
	for (var i = 0; i < list.length; i++) {
		next = list[i].innerHTML.split(",");
		mySeries.push([Date.parse(next[0]), parseInt(next[1])]);
	}
	
    $('#ProvGraph').highcharts({
        chart: {
            type: 'column'
        },
        title: {
            text: 'Change in Provisioned Desktops by date'
        },
        xAxis: {
        	type: 'datetime',
        	dateTimeLabelFormats: {
                month: '%e. %b',
                year: '%b'
            },
            title: {
                text: 'Date'
            }
        },
        yAxis: [{
            title: {
                text: 'Desktops'
            }
        }],
        series: [{
            name: 'Provisioned Desktops',
            color: 'rgba(165,170,217,1)',
            data: mySeries
        }]
    });
}

//Runs everytime the type of report changes
//Changes the page title and input if necessary
function changeType() {
	var typeSelect = document.getElementById("typeReport");
	var typeReport = typeSelect.options[typeSelect.selectedIndex].value;
	var title = document.getElementById("title");
	title.innerHTML = "<h2>" + typeSelect.options[typeSelect.selectedIndex].innerHTML + "</h2>";
	//Change the cid input to "All" when we switch to an Agent Report
	if (typeReport === "AgentReport") {
		document.getElementById("cidText").value = "All";
	};
	//Refresh page content to new report
	refreshPage();
}

// Updates the content in the page to match the filters
// Occurs anytime one of the filter values changes
function refreshPage() {
	console.log("Refreshing");
	var typeSelect = document.getElementById("typeReport");
	var scopeSelect = document.getElementById("scope");
	var typeReport = typeSelect.options[typeSelect.selectedIndex].value;

	//Validate the input to cidText is an element in the datalist
	var cid = document.getElementById("cidText").value;
	var opt = false;
	var cidList = document.getElementById("cidList");

	for (var i = cidList.options.length - 1; i >= 0; i--) {
		if (cidList.options[i].value == cid) {
			opt = true;
			break;
		};
	};

	//Start AJAX
	if (!opt) {
		document.getElementById("main-report").innerHTML="Please enter a valid Customer ID";
	} else {
		//Initialize XML HTTP Request
		var httpxml;
		try {
		// Firefox, Opera 8.0+, Safari
			httpxml=new XMLHttpRequest();
		}
		catch (e) {
			// Internet Explorer
			try {
				httpxml=new ActiveXObject("Msxml2.XMLHTTP");
			}
			catch (e) {
				try {
					httpxml=new ActiveXObject("Microsoft.XMLHTTP");
				}
				catch (e) {
					alert("Your browser does not support AJAX!");
					return false;
				}
			}
		}

		//For filling the html with the response text once request is complete
		function stateCheck() {
			if(httpxml.readyState == 4 && httpxml.status  == 200) {
				document.getElementById("main-report").innerHTML = httpxml.responseText;
				if(typeReport == "VSPPReport")
					vsppGraph();
				if(typeReport == "ProvReport")
					provGraph();
				if(typeReport == "AgentReport") {
					var re = /buildScrollTable\(([0-9]{5})\)/g;
					var m;
					 while (m = re.exec(httpxml.responseText.substring(re.lastIndex))) {
						console.log(m[0], m[1]);
						buildScrollTable(m[1]);
					}
				};
				var cidSelect = document.getElementById("custcid");
				if(cidSelect.style.display == "initial") {
					generateCustom();
				}
			} else if(httpxml.readyState <= 3) {
				document.getElementById("main-report").innerHTML = "<img src='img/ajax-loader.gif' class='loader'>";
			} else if(httpxml.status != 200) {
				document.getElementById("main-report").innerHTML = "<p><strong>Ooooops...</strong> Something went wrong with retrieving the report data</p><p><strong>Status code: " + httpxml.status + "</strong><br>" + httpxml.statusText + "</p>";
			};
		}

		//Build the URL with get request
		var url="index-query.php";
		url = url + "?cid=" + cid;
		url = url + "&type=" + typeReport;
		url = url + "&scope=" + scopeSelect.options[scopeSelect.selectedIndex].value;
		//console.log("sending request: " + url);

		//Open URL
		httpxml.onreadystatechange=stateCheck;
		httpxml.open("GET",url,true);
		httpxml.send(null);
	}
};

// converts HTML table to csv
function tableToCSV() {
	var table = document.getElementById("export").children;
	var str = "";
	for (var i = 0; i < table.length; i++) {
		for (var j = 0; j < table[i].children.length; j++) {
			for (var k = 0; k < table[i].children[j].children.length; k++) {
				str += table[i].children[j].children[k].innerHTML + ","
			};
			str = str.substring(0, str.length-1);
			str += "<br>";
		};
	};
	document.getElementById("print").innerHTML = str;
}

// Requests an export file be created by the server and downloads it
function exportTable() {
	var typeSelect = document.getElementById("typeReport");
	var scopeSelect = document.getElementById("scope");
	var exportSelect = document.getElementById("exportSelector");
	var url="export.php";
	url = url + "?cid=" + document.getElementById("cidText").value;
	url = url + "&type=" + typeSelect.options[typeSelect.selectedIndex].value;
	url = url + "&scope=" + scopeSelect.options[scopeSelect.selectedIndex].value;
	url = url + "&ext=" + exportSelect.options[exportSelect.selectedIndex].value;
	location.href=url;
}

// for managing expand/compress content buttons
function toggleAgent(cid, button) {
	var table = document.getElementById("table" + cid);
	var label = document.getElementById("cid" + cid);
	if (table.style.display == "none") {
		table.style.display = "initial";
	} else {
		table.style.display = "none";
	}
	if (button.hasClass("expand-button")) {
		button.removeClass("expand-button");
		button.addClass("collapse-button");
		var top = parseInt(label.style.top);
		label.style.top = "" + (top - 3) + "px";
		var left = parseInt(label.style.left);
		label.style.left = "" + (left + 4) + "px";
	} else {
		button.removeClass("collapse-button");
		button.addClass("expand-button");
		var top = parseInt(label.style.top);
		label.style.top = "" + (top + 3) + "px";
		var left = parseInt(label.style.left);
		label.style.left = "" + (left - 4) + "px";
		
	}

}

// For showing error details when a "more info" link is clicked in the Agent Report
function more_details(el) {
	var cid = el.parentElement.parentElement.children[0].innerHTML;
	var container = document.getElementById("expanded-content");
	container.style.visibility = "visible";
	var reports = container.children;
	var newEl = document.getElementById("expanded-" + cid);

	for (var i = 0; i < reports.length; i++) {
		reports[i].style.display = "none";
	};

	newEl.style.display = "initial";
}

function buildScrollTable(cid) {
	var tables = document.getElementsByClassName("scrollTable");

	for(var i = tables.length - 1; i >= 0; i--) {
		var childs = tables[i].children;
		var columnWidths = new Array();
		// Get column widths
		[].slice.call(childs[1].getElementsByTagName('th')).forEach(function (currentValue, index, array) {
			columnWidths[index] = currentValue.style.width;
		});

		// get column data for header
		var tableHeaderRow = childs[1].getElementsByTagName('thead').innerHTML;

		// clear header
		childs[1].getElementsByTagName('thead').innerHTML = "";
		// insert header data into new table container
		childs[0].getElementsByTagName('thead').innerHTML = tableHeaderRow;

		// modify column width for header
		[].slice.call(childs[0].getElementsByTagName('th')).forEach(function (currentValue, index, array) {
			currentValue.style.width = columnWidths[index];
		});

		// modify column width for the innerTable
		[].slice.call(childs[1].getElementsByTagName('td')).forEach(function (currentValue, index, array) {
			currentValue.style.width = columnWidths[index];
		});
	}
}

function toggleCustom() {
	var cidSelect = document.getElementById("custcid");
	var cidBtn = document.getElementById("custbtn");
	if(cidSelect.style.display == "none") {
		document.getElementById("cidText").value = "All";
		document.getElementById("cidText").disabled = true;
		refreshPage();
		cidSelect.style.display = "initial";
		cidBtn.style.display = "initial";
	} else {
		refreshPage();
		cidSelect.style.display = "none";
		cidBtn.style.display = "none";
		document.getElementById("cidText").disabled = false;
		for(var i = 0; i < cidSelect.options.length; i++) {
			cidSelect.options[i].selected = true;
		}
	}
}

function generateCustom() {
	var cidSelect = document.getElementById("custcid");
	var cids = Array();
	for(var i = 0; i < cidSelect.options.length; i++) {
		if(cidSelect.options[i].selected)
			cids.push(parseInt(cidSelect.options[i].innerHTML));
	};
	console.log(cids.toString());

	var typeSelect = document.getElementById("typeReport");
	var type = typeSelect.options[typeSelect.selectedIndex].value;
	var table = document.getElementById(type);
	var tbody;

	for(var i = 0; i < table.children.length; i++) {
		if(table.children[i].tagName.toLowerCase() === "tbody") {
			tbody = table.children[i];
		}
	};

	/*
	for(var j = 0; j < tbody.children.length; j++) {
		cids.push(parseInt(tbody.children[i].children[0].innerHTML));
	};
	*/

	var i = 0;
	var j = 0;
	var sumDtps = 0;
	var sumUsers = 0;
	while(i < cids.length && j < tbody.children.length) {
		console.log("Comparing: '" + cids[i] + "' and '" + tbody.children[j].children[0].innerHTML + "'");
		if(parseInt(tbody.children[j].children[0].innerHTML) < cids[i]) {
			tbody.children[j].addClass("hidden");
			j++;
		} else if(parseInt(tbody.children[j].children[0].innerHTML) > cids[i]) {
			i++;
		} else {
			tbody.children[j].removeClass("hidden");
			if(type == "VSPPReport") {
				sumDtps += parseInt(tbody.children[j].children[1].innerHTML);
				sumUsers += parseInt(tbody.children[j].children[2].innerHTML);
			}
			while(tbody.children[j] && parseInt(tbody.children[j].children[0].innerHTML) == cids[i]) {
				j++;
			}
			i++;
		}
	};
	
	while(j < tbody.children.length) {
		tbody.children[j].addClass("hidden");
		j++;
	}

	if(type == "VSPPReport") {
		var tfoot;
		for(var i = 0; i < table.children.length; i++) {
			if(table.children[i].tagName.toLowerCase() === "tfoot") {
				tfoot = table.children[i];
			}
		};

		tfoot.children[0].children[1].innerHTML = sumDtps;
		tfoot.children[0].children[2].innerHTML = sumUsers;
	}
}