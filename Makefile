all:
	if [[ -e bitrix-begateway-erip.zip ]]; then rm bitrix-begateway-erip.zip; fi
	 zip -r bitrix-begateway-erip.zip begateway.erip
