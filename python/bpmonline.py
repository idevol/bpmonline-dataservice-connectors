# -*- coding: UTF-8 -*-
import requests
import json

class BPMonline:
    __bpmonline_url = 'https://myproduct.bpmonline.com'
    
    __login_credentials = {'UserName': 'Supervisor', 'UserPassword': 'secret'}
    __session = None
    
    __login_uri  = '/ServiceModel/AuthService.svc/Login'
    __select_uri = '/0/dataservice/json/SyncReply/SelectQuery'
    __insert_uri = '/0/dataservice/json/reply/InsertQuery'
    __update_uri = '/0/dataservice/json/reply/UpdateQuery'

    def __init__(self):
        self.__login()

    def __login(self):
        headers = {'Content-Type': 'application/json'}
        self.__session = requests.post(self.__bpmonline_url + self.__login_uri, headers=headers, json=self.__login_credentials)
    
    def select(self, RootSchemaName, Columns):
        select_json = {
            "RootSchemaName":RootSchemaName,
            "OperationType":0,
            "Columns":{
                "Items":{
                    "Id":{
                        "Expression":{
                            "ExpressionType":0,
                            "ColumnPath":"Id"
                        }
                    }
                }
            },
            "allColumns": False,
            "useLocalization": True
        }

        for column in Columns:
            select_json["Columns"]["Items"].update({
                column:{
                    "caption": "",
                    "orderDirection": 0,
                    "orderPosition": -1,
                    "isVisible": True,
                    "Expression":{
                        "ExpressionType":0,
                        "ColumnPath":column
                    }
                }
            })
        
        select_url = self.__bpmonline_url + '/0/dataservice/json/SyncReply/SelectQuery'
        headers = {'Content-Type': 'application/json'}
        headers['BPMCSRF'] = self.__session.cookies.get_dict()['BPMCSRF']

        select_response = requests.post(select_url, headers=headers, cookies=self.__session.cookies, json=select_json)
        return json.loads(select_response.text)