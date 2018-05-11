var plaid = require('plaid');
var prompt = require('prompt');
var moment = require('moment');
var _ = require('underscore');
var exec = require('child_process').exec;
var mysql = require("mysql");
var plaid_env = plaid.environments.tartan
var client_id = "client_id"
var secret = "secret"
var plaidClient = new plaid.Client(client_id, secret, plaid_env);
var args = process.argv.slice(2);
var access_token = "access_token"

var getBalance = function(cb) {
	plaidClient.getBalance(access_token, function (err, response) {
		if (err != null) {
			cb(err, null)
		} else {
			var accounts = {}
			_.each (response.accounts, function (account) {
				accounts[account.meta.number] = {balance: account.balance.available, name: account.meta.name}
			})
			cb(null, accounts)
		}
	})
}
var getTransactions = function(cb) {
	plaidClient.getConnectUser(access_token, {
		gte: '16 days ago',
		pending: true
	}, function (err, response) {
		var accounts = {}
		var transactions = {}
		var balances = {}
		_.each(response.accounts, function (account) {
			accounts[account._id] = account.meta.number
			transactions[account.meta.number] = []
			balances[account.meta.number] = account.balance.available
		})
		_.each(response.transactions, function (transaction) {
			transactions[accounts[transaction._account]].push(transaction)
		})
		cb(transactions, balances)
	});
}

var stepUser = function(err, mfaResponse, response, cb) {
    if (err != null) {
    	cb(err, null)
    } else if (mfaResponse != null) {
    	prompt.start();
    	console.log(mfaResponse.mfa[0].question);
		prompt.get(['response'], function (err, result) {
			if (err != null) {
				cb(err, null)
			} else {
				plaidClient.stepConnectUser(mfaResponse.access_token, result.response, {},
			    function (err, mfaResponse, response) {
			    	stepUser(err, mfaResponse, response, cb)
				})
			}
		});
    } else {
    	cb(null, response)
    }
}

switch (args[0]) {
	case "login":
		prompt.start();
		prompt.get(['username', 'password'], function (err, result) {
			// Add a BofA auth user going through question-based MFA
			plaidClient.addConnectUser('bofa', {
				username: result.username,
				password: result.password,
				options: {"webhook": "https://my-server.com/bank.php"}
			}, function (err, mfaResponse, response) {
				if (err != null) {
					// Bad request - invalid credentials, account locked, etc.
					console.error(err);
				} else {
					stepUser(err, mfaResponse, response, function (err, response) {
						if (err != null) {
							console.log(err)
						} else {
							console.log(response)
						}
					})
				}
			});
		})
		break;
	case "balance":
		getBalance(function (err, accounts) {
			if (err != null) {
				console.log(err)
			} else {
				_.each (accounts, function (account, number) {
					console.log(number + ": $" + account.balance + " === " + account.name)
				})
			}
		})
		break;
	case "transactions":
		getTransactions(function (accounts, balances) {
			var t = {}
			_.each(accounts, function (account, index) {
				_.each(account, function (transaction) {
					if (!t[index]) t[index] = []
					// t[index].push(transaction)
					t_info = {"name":transaction.name, "amount":transaction.amount, "date":transaction.date}
					t[index].push(t_info)
				})
			})
			console.log(t)
		})
		break;
	case "available":
		var con = mysql.createConnection({
		  host: "localhost",
		  user: "user",
		  password: "password",
		  database: "db"
		});
		con.query('SELECT * FROM bills ORDER BY date', function(err, bills_sql){
		  	if(err) {
		  		console.log(err);
		  		process.exit();
		  	}
		  	var bills = {};
		  	_.each(bills_sql, function(bill) {
		  		bills[bill.name] = {"date": bill.date, "amount": bill.amount};
		  	});
			getTransactions(function (accounts, balances) {
				actualBalance = balances['1567']
				//remove amount to save
				if (bills.Save) {
					balances['1567'] -= bills.Save.amount
					balances['1567'] = Number(balances['1567'].toFixed(2))
					delete bills.Save
				}
				var d1 = 5
				var d2 = 20
				con.end(function(err) {
				});

				var set1 = {}
				var set2 = {}
				totalSet1 = 0
				totalSet2 = 0
				_.each(bills, function (bill, name) {
					if (bill.date >= d1 && bill.date < d2) {
						totalSet1 += bill.amount
						set1[name] = bill
					} else {
						totalSet2 += bill.amount
						set2[name] = bill
					}
				})
				set2.Misc.amount = ((totalSet1 + totalSet2) / 2) - totalSet2
				set2.Misc.amount = Number(set2.Misc.amount.toFixed(2))

				var current_date = moment().date()
				var pay_period_bills = set2
				var next_pay_period_bills = set1
				var start
				var end
				if (current_date >= d1 && current_date < d2) {
					pay_period_bills = set1
					next_pay_period_bills = set2
					start = moment().date(d1).startOf('day')
					end = moment().date(d2).subtract(1, 'day').endOf('day')
				} else if (current_date < d1) {
					start = moment().subtract(1,'month').date(d2).startOf('day')
					end = moment().date(d1).subtract(1, 'day').endOf('day')
				} else if (current_date >= d2) {
					start = moment().date(d2).startOf('day')
					end = moment().add(1,'month').date(d1).subtract(1, 'day').endOf('day')
				}

				var dues = 0
				var bills_left = ""
				var addNext = false
				var nextStart = moment(end).add(1,'day').day()
				_.each(accounts['1567'], function (transaction) {
					if (nextStart<1 && nextStart>5 && transaction.name.search('#^Paycheck#')!=-1 && moment(transaction.date).isBetween(moment(start).add(1, 'day'), end)) {
						addNext = true
					}
					_.each(pay_period_bills, function (info, name) {
						if (transaction.name.search(name)!=-1) {
							delete pay_period_bills[name]
						}
					})
				})
				if (addNext) {
					delete pay_period_bills.Misc
					_.extend(pay_period_bills, next_pay_period_bills)
				}
				_.each(pay_period_bills, function (bill, key) {
					dues += bill.amount
					bills_left += "\n" + bill.date + "\t | \t$" + bill.amount + "\t | \t"+ key
				})
				bills_left += "\n------------------------------------"
				bills_left += "\n=\t | \t$"+dues
				var balance_after_bills = (balances['1567'] - dues).toFixed(2)
				// var message = "Available after bills = " + balance_after_bills
				var message = "$" + balance_after_bills + " after" + "\t | \t$" + actualBalance + " before" + bills_left
				exec("~/sendSlackMessage misc node " + "'" + message + "'")
			})
		});

		break;
}