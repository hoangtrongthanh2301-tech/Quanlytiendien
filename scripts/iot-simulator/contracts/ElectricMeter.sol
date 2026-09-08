// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract ElectricMeter {
    // store latest reading per meter per month (month as YYYYMM)
    mapping(bytes32 => uint256) public readings;

    event ReadingRecorded(string indexed meterId, uint256 indexed month, uint256 timestamp, uint256 kwh, address sender);

    function _key(string memory meterId, uint256 month) internal pure returns (bytes32) {
        return keccak256(abi.encodePacked(meterId, month));
    }

    // record a reading for a meter for a specific month (YYYYMM)
    function recordReading(string calldata meterId, uint256 month, uint256 timestamp, uint256 kwh) external {
        bytes32 k = _key(meterId, month);
        readings[k] = kwh;
        emit ReadingRecorded(meterId, month, timestamp, kwh, msg.sender);
    }

    function getReading(string calldata meterId, uint256 month) external view returns (uint256) {
        return readings[_key(meterId, month)];
    }
}
