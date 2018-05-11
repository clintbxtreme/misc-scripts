running = false;
var brands = ["Always","Arrowhead","Ben\\ \\&\\ Jerry\\'s","belVita","Bounce","Bounty",
	"Breyers","Bush\\'s","Clorox","Charmin","Cheerios","Cheez-it","Coca-Cola","Comforts",
	"Cottonelle","Country\\ Crock","Crest","Dasani","Dawn","Dial","DIGIORNO","Dove","Energizer",
	"Febreze","Fiber\\ One","Frito-Lay","Gatorade","Guerrero","General\\ Mills","Ghirardelli",
	"Gillette","Glad","Goldfish","Hefty","Heinz","Hormel","Huggies","Hungry\\ Jack","Kellogg\\'s",
	"Kraft","Jennie-O","Jimmy\\ Dean","Listerine","Luvs","Lysol","Marie\\ Callender\\'s","Nabisco",
	"Naked\\ Juice","Nature\\ Valley","Old\\ Spice","Pampers","PediaSure","PHILADELPHIA","Playtex",
	"Rubbermaid","Sabra","Secret","Silk","Simple\\ Truth","Soft\\ Soap","Softsoap","Special\\ K",
	"Store\\ Brand","Swiffer","TAMPAX","Tide","Tostitos","TUMS","Venus","vitaminwater","Welch\\'s",
	"Windex","Ziploc","ZzzQuil"];

// var ignore_labels = ["Lithium","Men+Care® Hair Products","Always DISCREET","Dove® Beauty Bar"];

function clickCategories() {
	$(".all_categories input").prop("checked", true)
	$(".all_categories input").trigger("click")
}

function couponClicks() {
	if (running) {
		console.log("couponClicker->couponClicks is still running");
		return;
	}
	running = true;
	clickCategories();
	setTimeout(function(){
		var coupons_to_add = 0;
		for (var i=0; i<brands.length; i++) {
			if ($("#"+brands[i]).length) {
				coupons_to_add ++;
				$("#"+brands[i]).prop("checked", true)
				$("#"+brands[i]).trigger("click")
			}
		}
		if (!coupons_to_add) {
			alert("No new coupons to add");
			running = false;
		} else {
			setTimeout(function(){
				$('.coupon_action').trigger("click")
				running = false;
				console.log("couponClicker->couponClicks is done running");
				alert(coupons_to_add + " coupon(s) added");
			}, 1);
		}
	}, 1);
}

function loadCoupons(cb) {
	var list = $(".couponlist li")
	var buttons = $('.coupon_action')
	if (list.length != buttons.length) {
		list.trigger("focus");
		setTimeout(function() {
			loadCoupons(cb)
		},1)
	} else {
		cb();
	}
}

function couponClicksPhone() {
	if (running) {
		alert("couponClicker->couponClicksPhone is still running");
		return;
	}
	running = true;
	$(".all_categories option").attr("selected", "selected")
	$(".all_categories").trigger("change")
	setTimeout(function(){
		var brands_to_add = false;
		var current_brands = $(".current_brands option");
		current_brands.each(function(i, el){
			var v = el.value;
			var brand = v.replace(/\ \(.*/, "");
			if (brands.indexOf(brand) > -1) {
				brands_to_add = true;
				el.setAttribute("selected","selected");
			}
		})
		current_brands.parent().trigger("change");
		if (!brands_to_add) {
			alert("No new coupons to add");
			running = false;
		} else {
			setTimeout(function(){
				loadCoupons(function(){
					var coupon_buttons = $('.coupon_action')
					// coupon_buttons.each(function(i, el) {
					// 	var label = el.children[0].innerHTML;
					// 	for (var i=0; i<ignore_labels.length; i++) {
					// 	}
					// })
					var total_coupons = coupon_buttons.length
					coupon_buttons.trigger("click");
					running = false;
					console.log("couponClicker->couponClicks is done running");
					alert(total_coupons + " coupon(s) added");
				})
			}, 1);
		}
	}, 1);
}

// function brandsInverted() {
// 	clickCategories();

// 	setTimeout(function(){
// 		$(".current_brands input").prop("checked", true)
// 		$(".current_brands input").trigger("click")

// 		setTimeout(function(){
// 			for (var i=0; i<brands.length; i++) {
// 				if ($("#"+brands[i]).length) {
// 					$("#"+brands[i]).prop("checked", false)
// 					$("#"+brands[i]).trigger("click")
// 				}
// 			}
// 		}, 1000);
// 	}, 1000);
// }


// document.getElementsByClassName('unclip')[0].click();setTimeout(function(){document.getElementsByClassName("removecpn")[0].click();}, 300);


// javascript:(function(){if(typeof cpn_script=='undefined'){cpn_script=document.createElement('SCRIPT');cpn_script.type='text/javascript';cpn_script.src='https://server.com/couponClicker.js';cpn_script.onload=function(){couponClicks();};document.getElementsByTagName('head')[0].appendChild(cpn_script);}else{couponClicks();}})();

// javascript:(function(){if(typeof cpn_script=='undefined'){cpn_script=document.createElement('SCRIPT');cpn_script.type='text/javascript';cpn_script.src='https://server.com/couponClicker.js';cpn_script.onload=function(){couponClicksPhone();};document.getElementsByTagName('head')[0].appendChild(cpn_script);}else{couponClicksPhone();}})();
