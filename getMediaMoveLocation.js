#!/usr/bin/env node
var ptn = require('parse-name');
var args = process.argv.slice(2);
var base = "/home/user/Videos/";
var dir = base + "Movies";
var media = args[0];
var result = ptn(media);

String.prototype.toTitleCase = function(){
  var smallWords = /^(a|an|and|as|at|but|by|en|for|if|in|nor|of|on|or|per|the|to|vs?\.?|via)$/i;

  return this.replace(/[A-Za-z0-9\u00C0-\u00FF]+[^\s-]*/g, function(match, index, title){
    if (index > 0 && index + match.length !== title.length &&
      match.search(smallWords) > -1 && title.charAt(index - 2) !== ":" &&
      (title.charAt(index + match.length) !== '-' || title.charAt(index - 1) === '-') &&
      title.charAt(index - 1).search(/[^\s-]/) < 0) {
      return match.toLowerCase();
    }

    if (match.substr(1).search(/[A-Z]|\../) > -1) {
      return match;
    }

    return match.charAt(0).toUpperCase() + match.substr(1);
  });
};

if (result.season) {
	var title = "unknown";
	if (result.title) {
		title = result.title.toTitleCase();
	}
	dir = base + "TV Shows/"+title;
}
console.log(dir);