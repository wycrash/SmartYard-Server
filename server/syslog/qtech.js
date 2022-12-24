const syslog = new (require("syslog-server"))();
const { hw: { qtech } } = require("./config.json");
const { getTimestamp } = require("./utils/formatDate");
const { urlParser } = require("./utils/url_parser");
const API = require("./utils/api");
const { mdTimer } = require("./utils/mdTimer");
const { port } = urlParser(qtech);

const gateRabbits = [];

syslog.on("message", async ({ date, host, message }) => {
    const now = parseInt(getTimestamp(date));

    const qtMsg = message.split(" - - - ")[1].trim();
    const qtMsgParts = qtMsg.split(":").filter(Boolean).map(part => part.trim());

    // Фильтр сообщений, не несущих смысловой нагрузки
    if (qtMsg.indexOf("Heart Beat") >= 0 || qtMsg.indexOf("IP CHANGED") >= 0) {
        return;
    }

    console.log(`${new Date(date).toLocaleString("RU-ru")} || ${host} || ${qtMsg}`);

    // Отправка сообщения в syslog storage
    await API.sendLog({ date: now, ip: host, unit: "qtech", msg: qtMsg });

    //Открытие двери по ключу
    if (
        qtMsgParts[1] === "101" &&
        qtMsgParts[1] === "Open Door By Card, RFID Key"
    ) {
        await API.openDoor({host, detail: rfid, type: "rfid"});
    }

    /**
     * Попытка открытия двери не зарегистрированным ключем.
     * пока не используется
     */
    if (
        qtMsgParts[1] === "201" &&
        qtMsgParts[3] === "Open Door By Card Failed! RF Card Number"
    ) {
        console.log(":: Open Door By Card Failed!");
    }

    //TODO: разобратсья что передать в callFinished  по аналогии с beward
    if (qtMsgParts[1] === "000" && qtMsgParts[3] === "Finished Call") {
        console.log(":: Finished Call");
        await API.callFinished();
    }

    //Отктыие двери используя персональный код квартиры
    if (qtMsgParts[1] === "400" && qtMsgParts[4] === "Open Door By Code, Code") {
        console.log(":: Отктыие двери используя персональный код квартиры");
        const code = qtMsgParts[2];
        await API.openDoor({host, detail: code, type: "code"});
    }

    //Детектор движения
    if (qtMsgParts[1] === "000" && qtMsgParts[3] === "Send Photo") {
        await API.motionDetection({date: now, ip: host, motionStart: true});
        await mdTimer(host, 5000);
    }

    /**Открытие двери используя кнопку*/
    if (
        qtMsgParts[1] === "102" &&
        qtMsgParts[2] === "INPUTA" &&
        qtMsgParts[3] === "Exit button pressed,INPUTA"
    ) {
        await API.doorIsOpen(host);
    }
});

syslog.on("error", (err) => {
    console.error(err.message);
});

syslog.start({port}).then(() => console.log(`QTECH syslog server running on port ${port}`));
