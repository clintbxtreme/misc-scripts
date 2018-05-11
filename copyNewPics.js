#!/usr/bin/env node
var async = require('async')
var fs = require('fs-extra')
var exif = require('exiftool');
var _ = require('underscore')
var recursive = require('recursive-readdir-sync');
var rsync = require("rsyncwrapper");
var spawn = require('child_process').spawn;
var args = process.argv.slice(2);
var home_dir = process.env.HOME || process.env.HOMEPATH || process.env.USERPROFILE
var to_dir = home_dir + "/Pictures/from_canon_camera"
var search_dir = "/Volumes/Eye-Fi/DCIM"
var seriesTasks = []
var count = 0

function check(files, cb) {
	var extensions = ["jpg", "jpeg", "mov"]
	var directives = []
	var asyncTasks = []
	_.each(files, function(file_path) {
		var extension = file_path.split(".").pop()
		if (!_.contains(extensions, extension.toLowerCase())) return;
		asyncTasks.push(function(cb) {
			checkSingle(file_path, function(result) {
				if (result) {
					directives.push(result)
				}
				cb()
			})
		})
	})
	async.parallel(asyncTasks, function() {
		cb(directives)
	})

}

function checkSingle(file_path, cb) {
	var file = fs.readFileSync(file_path)
	var img = file_path.split("/").pop()
	exif.metadata(file, ["-dateTimeOriginal", "-model", "-createDate"], function (error, metadata) {
	 	if (error || !metadata) {
	 		console.error("EXIF Lookup Error for " + img + ": " + error.message)
	 		cb(false)
	 		return
	 	}

		var cameraModelName = metadata.cameraModelName
		var date = metadata['date/timeOriginal'] || metadata.createDate
		if (date) date = date.split(" ")[0].split(":").join("-")
		var dir = date || "-unknown"
    	var to_path = to_dir + "/" + dir + "/" + img
    	if (cameraModelName != "Canon EOS REBEL T3i") {
    		console.error("Removing " + file_path + " taken from " + cameraModelName)
    		fs.unlinkSync(file_path)
    		cb(false)
    	} else if (!fs.existsSync(to_path)) {
    		info = {from: file_path, to: to_path}
    		cb(info)
    	} else {
    		cb(false)
    	}
	})
}

function copyPics(directives) {
	_.each(directives, function(d) {
		if (!d.from || !d.to) return
		console.log("copying " + d.from.split("/").pop() + " to " + d.to)
		fs.copySync(d.from, d.to)
		if (!fs.existsSync(d.to)) {
			console.error("unable to copy to " + d.to)
		} else {
    		count ++
		}
	})
}

function execute(dir) {
	try {
		var files = recursive(dir)
		var chunks = []
		var size = 300;
		if (args[0] != "all") {
			files = filterFiles(files)
		}
		while (files.length > 0) {
			chunks.push(files.splice(0, size))
		}
		_.each(chunks, function(chunk) {
			seriesTasks.push(function(cb) {
				check(chunk, function(directives) {
					copyPics(directives)
					cb()
				})
			})
		})
		async.series(seriesTasks, function(){
			console.log("Total files coppied: " + count)
			console.log("Syncing to Server")

			spawn(home_dir+'/rsyncPicsLoop.sh', {
			    stdio: [
			    	'ignore',
			    	fs.openSync(home_dir+'/rsyncPicsLoop.log', 'a'),
			    	fs.openSync(home_dir+'/rsyncPicsLoop.log', 'a')
		    	],
			    detached: true
			}).unref();

			// var options = {
			// 	src: 		to_dir + "/",
			// 	dest: 		"user@my-server.com:/home/user/Pictures/from_canon_camera/",
			// 	ssh: 		true,
			// 	delete: 	true,
			// 	exclude: 	[".DS_Store"],
			// 	args: 		["-avh"]
			// }
			// rsync(options,function(error, stdout, stderr, cmd) {
			// 	if (error) {
			// 		console.error(cmd)
			// 		console.error(error.message)
			// 	} else {
			// 		console.log(cmd)
			// 		console.log(stdout)
			// 	}
			// })
		});
	} catch(err) {
		if(err.errno === 34){
	    	console.error('Path does not exist');
	  	} else {
	    	throw err;
	  	}
	}
}

function filterFiles(files) {
	var existing_dirs = fs.readdirSync(to_dir).filter(function (d) {
		return d.match(/\d{4}-\d{2}-\d{2}/);
	})
	if (!existing_dirs) return files
	var existing_newest_dir = _.max(existing_dirs, function (d) {
	    return d.replace(/-/g, "");
	});
	var existing_newest_time = Date.parse(existing_newest_dir);
	var files_to_copy = _.filter(files, function(file) {
	   return fs.statSync(file).mtime > existing_newest_time
	})
	console.log("found " + files_to_copy.length + " to check")
	return files_to_copy
}
execute(search_dir)