require('dotenv').config();
const { ethers } = require('ethers');
const abi = require('./abi/ElectricMeter.json');

const RPC_URL = process.env.RPC_URL;
const PRIVATE_KEY = process.env.PRIVATE_KEY;
const CONTRACT_ADDRESS = process.env.CONTRACT_ADDRESS;
const METER_ID = process.env.METER_ID || 'MTR-001';
const INTERVAL_SEC = process.env.INTERVAL_SEC ? parseInt(process.env.INTERVAL_SEC) : 60;
let lastKwh = process.env.START_KWH ? parseInt(process.env.START_KWH) : 1000;

if (!RPC_URL || !PRIVATE_KEY || !CONTRACT_ADDRESS) {
  console.error('Please set RPC_URL, PRIVATE_KEY and CONTRACT_ADDRESS in .env');
  process.exit(1);
}

const provider = new ethers.providers.JsonRpcProvider(RPC_URL);
const wallet = new ethers.Wallet(PRIVATE_KEY, provider);
const contract = new ethers.Contract(CONTRACT_ADDRESS, abi, wallet);

function monthYYYYMM(d) {
  return d.getUTCFullYear() * 100 + (d.getUTCMonth() + 1);
}

async function sendReading() {
  const now = Math.floor(Date.now() / 1000);
  const month = monthYYYYMM(new Date());
  // simulate small consumption
  lastKwh += Math.floor(Math.random() * 5);
  try {
    const tx = await contract.recordReading(METER_ID, month, now, lastKwh);
    console.log(new Date().toISOString(), 'sent', tx.hash, 'kWh=', lastKwh);
    await tx.wait();
    console.log(new Date().toISOString(), 'confirmed', tx.hash);
  } catch (e) {
    console.error('send failed', e.message || e);
  }
}

// send immediately then every INTERVAL_SEC
sendReading();
setInterval(sendReading, INTERVAL_SEC * 1000);
