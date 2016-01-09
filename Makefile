all:
	if [[ -e bitrix-devtm-erip.zip ]]; then rm bitrix-devtm-erip.zip; fi
	 zip -r bitrix-devtm-erip.zip devtm.erip
