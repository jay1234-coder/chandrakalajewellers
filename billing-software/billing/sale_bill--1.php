<?php
include '../includes/db.php';

$year = date('Y');

$result = mysqli_query(
    $conn,
    "SELECT id FROM bills ORDER BY id DESC LIMIT 1"
);

if(mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);
    $next = $row['id'] + 1;
}else{
    $next = 1;
}

$bill_no = $year . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Sale Bill</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>

.bill-box{
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.row{
    display:flex;
    gap:15px;
    margin-bottom:15px;
}

.row .field{
    flex:1;
}

label{
    display:block;
    margin-bottom:5px;
    font-weight:600;
}

table{
    width:100%;
    margin-top:20px;
}

input{
    width:100%;
}

.summary{
    width:350px;
    margin-left:auto;
    margin-top:20px;
}

.summary table td{
    padding:10px;
}

.amount-cell{
    min-width:120px;
    font-weight:bold;
}

.add-btn{
    margin-top:15px;
}

.actions{
    margin-top:20px;
}

</style>

</head>

<body>

<div class="main">

<h1>Sale Bill</h1>

<form method="POST">
<div class="bill-box">
<div class="row">

<div class="field">
<label>Bill No</label>
<input
type="text"
value="<?php echo $bill_no; ?>"
name="bill_no">
</div>

<div class="field">
<label>Date</label>
<input
type="date"
value="<?php echo date('Y-m-d'); ?>">
</div>

</div>

<div class="row">

<div class="field">
<label>Customer Name</label>
<input type="text">
</div>

<div class="field">
<label>Mobile</label>
<input type="text">
</div>

</div>

<div class="row">

<div class="field">
<label>Address</label>
<input type="text">
</div>

</div>

<table border="1">

<thead>

<tr>
<th>Sr</th>
<th>Description of Goods</th>
<th>HSN Code</th>
<th>Quantity (Gms)</th>
<th>Rate</th>
<th>Making %</th>
<th>Amount (Rs)</th>
<th>Action</th>
</tr>

</thead>

<tbody id="itemsBody">

<tr>

<td>1</td>

<td>
<input type="text" class="description">
</td>

<td>
<input type="text" class="hsn">
</td>

<td>
<input
type="number"
step="0.001"
class="qty calc">
</td>

<td>
<input
type="number"
step="0.01"
class="rate calc">
</td>

<td>
<input
type="number"
step="0.01"
class="making calc">
</td>

<td class="amount-cell amount">
0.00
</td>

<td>
<button
type="button"
onclick="removeRow(this)">
X
</button>
</td>

</tr>

</tbody>

</table>

<button
type="button"
class="add-btn"
onclick="addRow()">

+ Add Item

</button>

<div class="summary">

<table>

<tr>
<td>Subtotal</td>
<td id="subtotal">0.00</td>
</tr>

<tr>
<td>CGST (1.5%)</td>
<td id="cgst">0.00</td>
</tr>

<tr>
<td>SGST (1.5%)</td>
<td id="sgst">0.00</td>
</tr>

<tr>
<td><b>Net Amount</b></td>
<td id="net"><b>0.00</b></td>
</tr>

</table>

</div>

<div class="actions">

<button type="button">
Save Bill
</button>

<button onclick="window.print()">
Print Bill
</button>

</div>

</div>

</div>

<script>

function calculate(){

let subtotal = 0;

document.querySelectorAll('#itemsBody tr')
.forEach(row=>{

let qty =
parseFloat(
row.querySelector('.qty').value
) || 0;

let rate =
parseFloat(
row.querySelector('.rate').value
) || 0;

let making =
parseFloat(
row.querySelector('.making').value
) || 0;

let goldValue = qty * rate;

let makingAmount =
goldValue * (making / 100);

let total =
goldValue + makingAmount;

row.querySelector('.amount')
.innerHTML = total.toFixed(2);

subtotal += total;

});

let cgst =
subtotal * 1.5 / 100;

let sgst =
subtotal * 1.5 / 100;

let net =
subtotal + cgst + sgst;

document.getElementById('subtotal')
.innerHTML = subtotal.toFixed(2);

document.getElementById('cgst')
.innerHTML = cgst.toFixed(2);

document.getElementById('sgst')
.innerHTML = sgst.toFixed(2);

document.getElementById('net')
.innerHTML = net.toFixed(2);

}

document.addEventListener(
'input',
function(e){

if(
e.target.classList.contains('calc')
){
calculate();
}

}
);

function addRow(){

let tbody =
document.getElementById('itemsBody');

let sr =
tbody.rows.length + 1;

let row = tbody.insertRow();

row.innerHTML = `
<td>${sr}</td>

<td>
<input type="text" class="description">
</td>

<td>
<input type="text" class="hsn">
</td>

<td>
<input type="number"
step="0.001"
class="qty calc">
</td>

<td>
<input type="number"
step="0.01"
class="rate calc">
</td>

<td>
<input type="number"
step="0.01"
class="making calc">
</td>

<td class="amount">
0.00
</td>

<td>
<button
type="button"
onclick="removeRow(this)">
X
</button>
</td>
`;

}

function removeRow(btn){

btn.parentElement.parentElement.remove();

calculate();

}

</script>
</div>
</form>
</body>
</html>