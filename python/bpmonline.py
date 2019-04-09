# -*- coding: UTF-8 -*-
import datetime
import requests
import json
import pickle
from pathlib import Path
import os.path
import time

"""
https://github.com/idevol/bpmonline-dataservice-connectors
"""

class BPMonline:
    __bpmonline_url = 'https://myproduct.bpmonline.com'
    
    __login_credentials = {'UserName': 'Supervisor', 'UserPassword': 'secret'}
    __login_cookie_filename = 'bpmonline.session.cookie'
    
    __login_uri  = '/ServiceModel/AuthService.svc/Login'
    __select_uri = '/0/dataservice/json/SyncReply/SelectQuery'
    __insert_uri = '/0/dataservice/json/reply/InsertQuery'
    __update_uri = '/0/dataservice/json/reply/UpdateQuery'
    __delete_uri = '/0/dataservice/json/reply/DeleteQuery'

    __session = None
    __session_create = None
    __session_timeout = 60
    __session_header = {}
    __json_header = {'Content-Type': 'application/json'}

    def __init__(self):
        self.__session_validator()

    def __login(self):
        out = False
        self.__session = requests.post(self.__bpmonline_url + self.__login_uri, headers=self.__json_header, json=self.__login_credentials)
        if 'Content-Type' in self.__session.headers:
            if 'application/json' in self.__session.headers['Content-Type']:
                if 'BPMCSRF' in self.__session.cookies.get_dict():
                    self.__session_header = self.__json_header
                    self.__session_header.update({'BPMCSRF': self.__session.cookies.get_dict()['BPMCSRF']})
                    self.__session_create = datetime.datetime.now()
                    filehandler = open(self.__login_cookie_filename, 'wb') 
                    pickle.dump(self.__session, filehandler)
                    filehandler.close
                    out = True
                else:
                    self.__session = False
                    out = False
        return out

    def __session_lifetime(self):
        if self.__session == False:
            return 0
        if self.__session_create != None:
            return (datetime.datetime.now() - self.__session_create).total_seconds()
        else:
            cookie_file = Path(self.__login_cookie_filename)
            if cookie_file.is_file():
                filehandler = open(self.__login_cookie_filename, 'rb') 
                self.__session =    pickle.load(filehandler)
                self.__session_create = datetime.datetime.fromtimestamp(os.path.getmtime(self.__login_cookie_filename))
                return (datetime.datetime.now() - self.__session_create).total_seconds()
            else:
                return (self.__session_timeout + 1)

    def __session_validator(self):
        out = False
        if self.__session != None:
            if self.__session != False:
                if (self.__session_lifetime() > self.__session_timeout):
                    if self.__login():
                        out = True
                else:
                    out = True
            else:
                if self.__login():
                    out = True
        else:
            if self.__login():
                out = True
        return out
    
    def __filters(self, Query = {}, Filters = None):
        if (Filters != None):
            if (isinstance(Filters, str)):
                Query['filters'] = {
                    'items': {
                        'primaryColumnFilter': {
                            'filterType': 1,
                            'comparisonType': 3,
                            'isEnabled': True,
                            'trimDateTimeParameterToDate': False,
                            'leftExpression': {
                                'expressionType': 1,
                                'functionType': 1,
                                'macrosType': 34
                            },
                            'rightExpression': {
                                'expressionType': 2,
                                'parameter': {
                                    'dataValueType': 0,
                                    'value': Filters
                                }
                            }
                        }
                    },
                    'logicalOperation': 0,
                    'isEnabled': True,
                    'filterType': 6
                }
            elif (Filters['items'] != None):
                if (isinstance(Filters['items'], dict)):
                    if (len(Filters['items']) > 0):

                        LogicalOperatorType = 0
                        if ('logicalOperation' in Filters):
                            LogicalOperatorType = Filters['logicalOperation']
                        
                        Query['filters'] = {
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
                            
                            Query['filters']['items']['CustomFilters']['items'].update({
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
            elif (Filters['filters'] != None):
                Query['filters'] = Filters['filters']
        return Query

    def select_json(self, RootSchemaName, Columns = None, Filters = None):
        if self.__session_validator():
            select_query = {
                'RootSchemaName':RootSchemaName,
                'OperationType':0,
                'allColumns': False,
                'useLocalization': True
            }

            if Columns == None:
                select_query['allColumns'] = True
            else:
                select_query['Columns'] = {
                    'Items':{
                        'Id':{
                            'Expression':{
                                'ExpressionType':0,
                                'ColumnPath':'Id'
                            }
                        }
                    }
                }

                for column in Columns:
                    if (column == 'Name'):
                        select_query['Columns']['Items'].update({
                            column:{
                                'orderDirection': 1,
                                'isVisible': True,
                                'Expression':{
                                    'ExpressionType':0,
                                    'ColumnPath':column
                                }
                            }
                        })
                    elif (column == 'CreatedOn'):
                        select_query['Columns']['Items'].update({
                            column:{
                                'orderDirection': 2,
                                'isVisible': True,
                                'Expression':{
                                    'ExpressionType':0,
                                    'ColumnPath':column
                                }
                            }
                        })
                    else:
                        select_query['Columns']['Items'].update({
                            column:{
                                'isVisible': True,
                                'Expression':{
                                    'ExpressionType':0,
                                    'ColumnPath':column
                                }
                            }
                        })

            if (Filters != None):
                select_query = self.__filters(select_query, Filters)

            select_url = self.__bpmonline_url + self.__select_uri
            select_response = requests.post(select_url, headers=self.__session_header, cookies=self.__session.cookies, json=select_query)
            return select_response.text
        else:
            return '{}'
    
    def select(self, RootSchemaName, Columns = None, Filters = None):
        select_response_json = self.select_json(RootSchemaName, Columns, Filters)
        return json.loads(select_response_json)

    def insert_json(self, RootSchemaName, ColumnValuesItems = {}):
        if self.__session_validator():
            """
            ColumnValuesItems = {
                'Column1':{
                    'ExpressionType':2,
                    'Parameter':{
                        'DataValueType':1,
                        'Value':'New Text Value'
                    }
                }
            }
            """

            insert_query = {
                'RootSchemaName':RootSchemaName,
                'OperationType':1,
                'ColumnValues':{
                    'Items':ColumnValuesItems
                }
            }

            insert_url = self.__bpmonline_url + self.__insert_uri
            insert_response = requests.post(insert_url, headers=self.__session_header, cookies=self.__session.cookies, json=insert_query)
            return insert_response.text
        else:
            return '{}'

    def insert(self, RootSchemaName, ColumnValuesItems = {}):
        insert_response_json = self.insert_json(RootSchemaName, ColumnValuesItems)
        return json.loads(insert_response_json)
    
    def update_json(self, RootSchemaName, ColumnValuesItems = {}, Filters = None):
        if self.__session_validator():
            """
            ColumnValuesItems = {
                'Column1':{
                    'ExpressionType':2,
                    'Parameter':{
                        'DataValueType':1,
                        'Value':'New Text Value'
                    }
                }
            }

            Filters = {
                'logicalOperation':0,
                'items':{
                    'Id':{
                        'comparisonType':3,
                        'dataValueType':0, 
                        'value':'00000000-0000-0000-0000-000000000000'
                    }
                }
            }
            """

            if ColumnValuesItems != None:
                update_query = {
                    'RootSchemaName':RootSchemaName,
                    'OperationType':1,
                    'ColumnValues':{
                        'Items':ColumnValuesItems
                    }
                }

                if (Filters != None):
                    update_query = self.__filters(update_query, Filters)

                update_url = self.__bpmonline_url + self.__update_uri
                update_response = requests.post(update_url, headers=self.__session_header, cookies=self.__session.cookies, json=update_query)
                return update_response.text
            else:
                return '{"error":"ColumnValuesItems is None"}'
        else:
            return '{"error":"No session"}'

    def update(self, RootSchemaName, ColumnValuesItems = {}, Filters = None):
        update_response_json = self.update_json(RootSchemaName, ColumnValuesItems, Filters)
        return json.loads(update_response_json)

    def delete_json(self, RootSchemaName, Filters = None):
        if (Filters != None):
            delete_query = {
                'RootSchemaName':RootSchemaName,
                'OperationType':3,
                'ColumnValues':{}
            }

            delete_query = self.__filters(delete_query, Filters)

            delete_url = self.__bpmonline_url + self.__delete_uri
            delete_response = requests.post(delete_url, headers=self.__session_header, cookies=self.__session.cookies, json=delete_query)
            return delete_response.text
        else:
            return '{"error":"No query filters"}'

    def delete(self, RootSchemaName, Filters = None):
        delete_json = self.delete_json(RootSchemaName, Filters)
        return json.loads(delete_json)

    def lookup_json(self, RootSchemaName, ColumnValuesItems = ['Id','Name']):
        return self.select_json(RootSchemaName, ColumnValuesItems)

    def lookup(self, RootSchemaName, ColumnValuesItems = ['Id','Name']):
        lookup_json = self.lookup_json(RootSchemaName, ColumnValuesItems)
        return json.loads(lookup_json)