IoT Simulator for Electric Meter -> Smart Contract

Overview
- `ElectricMeter.sol` is a minimal smart contract that stores a reading per meter per month and emits an event when a reading is recorded.
- `simulate.js` is a Node.js script that periodically sends simulated meter readings to the deployed contract.

Quick start
1. Deploy `ElectricMeter.sol` (use Remix or Hardhat). Copy the deployed address.
2. Create `.env` in `scripts/iot-simulator` with values from `.env.example`.
3. Install dependencies:

```bash
cd scripts/iot-simulator
npm install
```

4. Run the simulator:

```bash
node simulate.js
```

Notes
- Use a local testnet (Ganache, Hardhat node) or a testnet RPC. Do NOT use a mainnet private key here.
- The contract and ABI are included for convenience. For production, review access controls and gas considerations.

Contract reference: `contracts/ElectricMeter.sol`
