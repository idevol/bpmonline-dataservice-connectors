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
    
    def select_json(self, RootSchemaName, Columns, Filters = None):
        select_query = {
            'RootSchemaName':RootSchemaName,
            'OperationType':0,
            'Columns':{
                'Items':{
                    'Id':{
                        'Expression':{
                            'ExpressionType':0,
                            'ColumnPath':'Id'
                        }
                    }
                }
            },
            'allColumns': False,
            'useLocalization': True
        }

        for column in Columns:
            select_query['Columns']['Items'].update({
                column:{
                    'caption': '',
                    'orderDirection': 0,
                    'orderPosition': -1,
                    'isVisible': True,
                    'Expression':{
                        'ExpressionType':0,
                        'ColumnPath':column
                    }
                }
            })

        if (Filters != None):
            if (Filters['items'] != None):
                if (isinstance(Filters['items'], dict)):
                    if (len(Filters['items']) > 0):

                        LogicalOperatorType = 0
                        if ('logicalOperation' in Filters):
                            LogicalOperatorType = Filters['logicalOperation']
                        
                        select_query['filters'] = {
                            'logicalOperation':0,
                            'isEnabled':True,
                            'filterType':6,
                            'items':{
                                'CustomFilters':{
                                    'logicalOperation':LogicalOperatorType,
                                    'isEnabled':True,
                                    'filterType':6,
                                    'items':{}
                                }
                            }
                        }

                        for Column, parameter in Filters['items'].items():
                            # EQUAL
                            comparisonType = 3
                            if ('comparisonType' in parameter):
                                comparisonType = parameter['comparisonType']

                            dataValueType = 0
                            if ('dataValueType' in parameter):
                                dataValueType = parameter['dataValueType']

                            value = ''
                            if ('value' in parameter):
                                value = parameter['value']
                            
                            select_query['filters']['items']['CustomFilters']['items'].update({
                                'customFilter' + Column + '_PHP':{
                                    'filterType':1,
                                    'comparisonType':comparisonType,
                                    'isEnabled':True,
                                    'trimDateTimeParameterToDate':False,
                                    'leftExpression':{
                                        'expressionType':0,
                                        'columnPath':Column
                                    },
                                    'rightExpression':{
                                        'expressionType':2,
                                        'parameter':{
                                            'dataValueType':dataValueType,
                                            'value':value
                                        }
                                    }
                                }
                            })

        
        select_url = self.__bpmonline_url + self.__select_uri
        headers = {'Content-Type': 'application/json'}
        headers['BPMCSRF'] = self.__session.cookies.get_dict()['BPMCSRF']

        select_response = requests.post(select_url, headers=headers, cookies=self.__session.cookies, json=select_query)
        return select_response.text
    
    def select(self, RootSchemaName, Columns, Filters = None):
        select_response_json = self.select_json(RootSchemaName, Columns, Filters)
        return json.loads(select_response_json)
