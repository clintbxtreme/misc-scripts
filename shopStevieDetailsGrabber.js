function downloadCsv() {
	getCsv(function(d) {
	    var dataStr = "data:text/csv;charset=utf-8," + d;
	    var encodedUri = encodeURI(dataStr);
		var link = document.createElement("a");
		link.setAttribute("href", encodedUri);
		link.setAttribute("download", "import.csv");
		document.body.appendChild(link);
		link.click();
		link.remove();
	})
}

function getDetails() {
	objs = shop.productSingleObject
	count = Math.max(objs.variants.length, objs.images.length);
	for (var i = 0; i < count; i++) {
		if (typeof string === 'undefined') {
			var string = [objs.title,'"'+objs.description+'"',objs.vendor,objs.type,'false','Size'].join(',')
		} else {
			string += ',,,,,'
		}
		string += ','+objs.handle
		if (typeof objs.variants[i] !== 'undefined') {
			variant = objs.variants[i]
			price = Math.max(variant.compare_at_price,variant.price)/100
			string += ','+variant.public_title
			string += ','+variant.sku
			string += ','+variant.weight
			string += ',shopify'
			string += ',0'
			string += ',deny'
			string += ',manual'
			string += ','+price
			string += ',true'
			string += ',false'
			string += ',oz'
		} else {
			string += ',,,,,,,,,,,'
		}
		if (typeof objs.images[i] !== 'undefined') {
			img =
			string += ','+'https:'+objs.images[i]
			string += ','+(i+1)
		} else {
			string += ',,'
		}
		string += "\n"
	}
	appendCsv(string, function() {
		if (window.confirm('Download?')) {
			window.downloadCsv()
		};
	})
}

function getCsv(callback) {
	params = {'a':'get'}
	cll(params, callback)
}

function appendCsv(data, callback) {
	params = {'a':'append','d':data}
	cll(params, callback)
}

function cll(params, callback) {
	params['scrt'] = 'asdfpwoiernakjnvaskfn'
	jQuery.post('https://my-serve.com/shopStevieDetailsGrabber.php', params, callback).fail(function() {
    	alert("error with "+params['a']);
  	})

}

// javascript:(function(){if(typeof ssg_script=='undefined'){ssg_script=document.createElement('SCRIPT');ssg_script.type='text/javascript';ssg_script.src='https://my-serve.com/shopStevieDetailsGrabber.js';ssg_script.onload=function(){getDetails();};document.getElementsByTagName('head')[0].appendChild(ssg_script);}else{getDetails();}})();