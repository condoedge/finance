/* Bill / Invoice items */
function calculateTotals(){
	
	let subtotal = 0
	let total = 0
	let taxes = {}

	$('#finance-items tbody tr').each(function(){
		let row = $(this)
		let qty = parseFloat(row.find('[name=quantity_chd]')[0].value || 0)
		let price = parseFloat(row.find('[name=price_chd]')[0].value || 0)
		let itemAmount = qty * price

		let totalTaxRate = 0
		row.find('.item-taxes')[0].innerHTML = ''
		row.find('.vlTaggableContent [data-tax]').each(function(){
			let taxRate = parseFloat($(this).data('tax'))
			let taxId = $(this).data('id')
			totalTaxRate += taxRate
			row.find('.item-taxes')[0].innerHTML += '<div>'+asCurrency(itemAmount * taxRate)+'</div>'
			taxes[taxId] = (taxes[taxId] || 0) + itemAmount * taxRate
		})

		subtotal += itemAmount
		total += Math.round(itemAmount * (1 + totalTaxRate) * 100) / 100
		setRoundedAmount(row.find('.item-total'), itemAmount)
	})

	setRoundedAmount($('#finance-subtotal span'), subtotal)
	$('.tax-summary').each(function(){
		let row = $(this)
		let taxId = $(this).data('id')
		setRoundedAmount(row.find('.ccy-amount span'), taxes[taxId] || 0)
	})
	setRoundedAmount($('#finance-total span'), total)
}

function setRoundedAmount(selector, amount) {
	if (!selector[0]) {
		console.log('prob setting amount', amount)
	}
	selector[0].innerHTML = asCurrency(amount)
}

/* Initial Balances */
function calculateTotalBalances()
{
	var totalDebit = 0;
	var totalCredit = 0;

	$(".group-balances").each(function(){

		let group = $(this).data('group')
		let debit = getBalanceAmount($(this), ".debit-balance")
		let credit = getBalanceAmount($(this), ".credit-balance")

		totalDebit += debit
		totalCredit += credit

		$('#total-debit-'+group).html(asCurrency(debit))
		$('#total-credit-'+group).html(asCurrency(credit))
		$('#total-net-'+group+' .net-ccy').html(asCurrency(Math.abs(credit - debit)))
		$('#total-net-'+group+' .net-side').html(debit > credit ? 'dt' : (debit < credit ? 'ct' : ''))

	})

	$(".accounts-balances-list").each(function(){

		let debit = getBalanceAmount($(this), ".debit-balance")
		let credit = getBalanceAmount($(this), ".credit-balance")

		$(this).find('.balance-debit-subcode').html(asCurrency(debit))
		$(this).find('.balance-credit-subcode').html(asCurrency(credit))

	})

	$('#total-debit-allgroups').html(asCurrency(totalDebit))
	$('#total-credit-allgroups').html(asCurrency(totalCredit))
	$('#total-net-allgroups .net-ccy').html(asCurrency(Math.abs(totalCredit - totalDebit)))
	$('#total-net-allgroups .net-side').html(totalDebit > totalCredit ? 'dt' : (totalDebit < totalCredit ? 'ct' : ''))
}

function getBalanceAmount(that, inputClass){
	var amount = 0
	that.find(inputClass).each(function(){
		amount += parseFloat($(this).val() || 0)
	})
	return amount
}

function toggleGroup(groupId)
{
	$('#group-block'+groupId).slideToggle()
	toggleIcon('#group-toggle'+groupId)
}

function toggleSubGroup(subgroupId)
{
	$('.subgroup-add'+subgroupId).toggleClass('hidden')
	$('.subgroup-block'+subgroupId).slideToggle()
	toggleIcon('#subgroup-toggle'+subgroupId)	
}

function toggleIcon(id)
{
	let toggleIcon = findToggleIcon(id)

	if (toggleIcon.css('rotate') == '180deg') {
		toggleIcon.css('rotate', '0deg')
	} else {
		toggleIcon.css('rotate', '180deg')
	}
}

function findToggleIcon(id)
{
	let downIcon = $(id).find('.icon-down')
	let upIcon = $(id).find('.icon-up')

	return downIcon.length ? downIcon : (upIcon.length ? upIcon : null);
}

/* Reconciliations */
function checkReconciliationAmount()
{
	setTimeout(function(){
		var toReconcile = $('#closing_balance_input').val() - $('#opening_balance_input').val()
		var reconciled = 0

		$(".recon-row").each(function(){

			let hasCheckedRow = $(this).find('.recon-check input').eq(0).attr('aria-checked')

			let debit = getReconAmount($(this), ".recon-debit span")
			let credit = getReconAmount($(this), ".recon-credit span")

			reconciled += hasCheckedRow ? (debit - credit) : 0

		})

		let reconciledAmount = reconciled.toFixed(2)
		let remainingAmount = (toReconcile - reconciled).toFixed(2)

		$('#recon_resolved').val(reconciledAmount)
		$('#recon_remaining').val(remainingAmount)
	}, 100) //because checkAll takes 50ms...
}

function getReconAmount(that, inputClass){
	return  parseFloat(that.find(inputClass).eq(0).html().replace('$ ', '').replace(',', '').replace('-', 0))
}

/* General - TODO: move to Kompo */
function checkAllCheckboxes(){
    setTimeout(function(){
    	$('.child-checkbox input').each(function(){
    		if(document.getElementById('checkall-checkbox').checked != $(this)[0].checked) {
	            $(this).click()
	        }
    	})
    }, 50)
}

function asCurrency(amount)
{
    if (!amount) {
        return '-'
    }

    return '$ '+amount.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}