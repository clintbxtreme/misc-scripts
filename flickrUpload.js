var Flickr = require("flickrapi")
var fs = require('fs-extra')
var util = require('util')
var path = require('path')
var mime = require('mime')
var flags = require('flags')
var winston = require('winston')
var _ = require('underscore')

flags.defineString('dir', '', 'Picture directory');
flags.defineString('user', '', 'User');

flags.parse();
var config = {}
config.dir = flags.get('dir')
var user = flags.get('user')

if (!user) {
	console.log("no user specified");
	process.exit();
}

config.dir = config.dir || process.cwd()

var dirPieces = config.dir.split("/")
dirPieces = _.filter(dirPieces, function(piece) {return piece != ""})
config.set = dirPieces.pop().replace(/\\/g, '')
var files = fs.readdirSync(config.dir)

config.picsByName = []
for(var i in files) {
	if(mime.lookup(files[i]) === 'image/jpeg') {
		var name = files[i].split(".").shift()
		var pic = {}
		pic[name] = files[i]
		config.picsByName.push(pic)
	}
}

// clint's

var options = {
	"clint":{ //clintburnham88
		api_key: "api-key",
		secret: "secret",
		user_id: "user_id",
		access_token: "access_token",
		access_token_secret: "access_token_secret",
		permissions: "delete",
		nobrowser: true,
		noAPI: true,
		force_auth: true
	},
	"flickrfs":{
		api_key: "api_key",
		secret: "secret",
		// user_id: "user_id",
		// access_token: "access_token",
		// access_token_secret: "access_token_secret",
		permissions: "delete",
		nobrowser: true
	}
}
config.flickrOptions = options[user];
var upload = function(cb) {
	var pics = []
	_.each(config.picsByName, function(filename, name) {
		var pic = {
			hidden: 2,
			is_public: 0,
			is_friend: 0,
			is_family: 0,
			title: name,
			photo: config.dir + "/" + filename
		}
   		pics.push(pic)
	})
	config.uploadOptions = {
		photos: pics
	}
	Flickr.upload(config.uploadOptions, config.flickrOptions, function(err, result) {
		if(err) {
		  console.error(error);
		  process.exit();
		}
		console.log(result);
	});
}
var createPhotoset = function(cb) {

}
var addPhotosToSet = function() {

}
var filterPhotosIfInPhotoset = function(cb) {
	var opts = {
		photoset_id: config.setID,
		api_key: config.flickrOptions.api_key,
		user_id: config.flickrOptions.user_id,
	}
	// _.extend(opts, config.flickrOptions, {photoset_id: config.setID, privacy_filter: 5, extras: "date_upload"})
	config.flickr.photosets.getPhotos(opts, function(err, result) {
		console.log("why not!")
		if (err) {
			console.log(err)
			process.exit()
		}
		console.log(result)
	})
}
Flickr.authenticate(config.flickrOptions, function(error, flickr) {
	if (error) {
		console.log(error)
		process.exit()
	}
	config.flickr = flickr
	config.flickr.photosets.getList(config.flickrOptions, function(err, data) {
		if (err) {
			console.log(err)
			process.exit()
		}
		_.each(data.photosets.photoset, function(photoset){
			if (photoset.title._content == config.set) {
				config.setID = photoset.id
				// filterPhotosIfInPhotoset(upload(addPhotosToSet()))
				filterPhotosIfInPhotoset()
			} else {
				// createPhotoset(upload(addPhotosToSet()))
			}
		})
		process.exit()
	})

});