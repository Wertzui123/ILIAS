var TextMessage = require('./TextMessage');

/**
 * A targeted text message
 *
 * @constructor
 */
var TargetMessage = function TargetMessage() {};

/**
 * @param {string} content
 * @param {number} roomId
 * @param {{id: number, username: string}} from
 * @param {{color: string, style: string, size: string, family: string}} format
 * @param {{username: string, id: number, public: number }} target
 *
 * @returns {{type: string, timestamp: number, content: string, roomId: number, from, format, target}}
 */
TargetMessage.prototype.create = function(content, roomId, from, format, target) {
	var message = TextMessage.create(content, roomId, from, format);

	message.target = target;

	return message;
};

/**
 * @type {TargetMessage}
 */
module.exports = new TargetMessage();
