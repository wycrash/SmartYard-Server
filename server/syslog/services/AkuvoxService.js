const { SyslogService } = require("./SyslogService")
const { API } = require("../utils");
const { SERVICE_AKUVOX } = require("../constants");
const { mdTimer} = require("../utils/mdTimer");

class AkuvoxService extends SyslogService {
    constructor(config) {
        super(SERVICE_AKUVOX, config);
    }

    filterSpamMessages(msg) {
        const akuvoxSpamKeywords = [
            "Couldn't resolve host name",
            "AKUVOX DCLIENT",
            "Autoprovision",
            "RFID szBuf",
            "lighttpd",
            "api.fcgi",
            "fcgiserver",
            "sipmain",
            "RFID_TYPE_WIEGAND",
            "netconfig",
            "Invalid SenderSSRC",
            "Listen",
            "Waiting",
            "Sending",
            "don't support play dtmf kecode",
            "Upload Server is empty",
            "spk not enable now!"
        ];

        return akuvoxSpamKeywords.some(keyword => msg.includes(keyword));
    }

    async handleSyslogMessage(now, host, msg) {
        //  Motion detection: start
        if (msg.indexOf("Requst SnapShot") >= 0) {
            await API.motionDetection({ date: now, ip: host, motionActive: true });
            await mdTimer(host);
        }

        //  Opening door by DTMF
        if (msg.indexOf("DTMF_LOG:From") >= 0) {
            const apartmentId = parseInt(msg.split(" ")[1].substring(1));
            await API.setRabbitGates({ date: now, ip: host, apartmentId });
        }

        // Opening door by RFID key
        if (msg.indexOf("OPENDOOR_LOG:Type:RF") >= 0) {
            const [ _, rfid, status ] = msg.match(/KeyCode:(\w+)\s*(?:Relay:\d\s*)?Status:(\w+)/);
            if (status === "Successful") {
                await API.openDoor({ date: now, ip: host, detail: '000000' + rfid, by: "rfid" });
            }
        }

        // Opening door by button pressed
        if (msg.indexOf("OPENDOOR_LOG:Type:INPUT") >= 0) {
            await API.openDoor({ date: now, ip: host, door: 0, detail: "main", by: "button" });
        }

        // All calls are done
        if (msg.indexOf("SIP_LOG:Call Failed") >= 0 || msg.indexOf("SIP_LOG:Call Finished") >= 0) {
            const callId = parseInt(msg.split("=")[1]); // after power on starts from 200002 and increments
            await API.callFinished({ date: now, ip: host, callId: callId});
        }
    }
}
module.exports= { AkuvoxService }